<?php

require_once 'Connection.php';
require_once 'Cache.php';
require_once 'Utils.php';
require_once 'Helpers.php';

class Oahu_Client {
    
  static $version = "0.1.0";
  static $dateFormat = "Y-m-d H:i:s O";

  public $debug = false;
  
  static $modelTypes   = array(
    'Project'   => array('Project'),
    'Resource'  => array('Image', 'Video', 'ImageList', 'VideoList')
  );
   
  static $modelFields  = array(
    'Project'               => array("title", "release_date", "credits",  "genres", "synopsis", "stylesheet_url", "homepage", "countries", "default_image_id", "default_video_id",  "published", "tags"),
    'Resource'              => array('source', 'name', 'description', 'published'),
    'Resources::ImageList'  => array('name', 'description', 'image_ids', 'published'),
    'Resources::VideoList'  => array('name', 'description', 'video_ids', 'published'),
    'List'                  => array('name', 'description', 'item_ids', 'tags')
  );
  
  static $configKeys = array('debug', 'host', 'clientId', 'projectId', 'appId', 'appSecret');

  static $defaultConfig = array('host' => 'app.oahu.fr', 'debug' => false);

  function Oahu_Client($o_config=array()){

    $config = self::parseConfig($o_config['oahu']);

    $this->config = $config;
    
    $this->host        = $config['host'];
    $this->clientId    = $config['clientId'];
    $this->projectId   = $config['projectId'];
    $this->appId       = $config['appId'];
    $this->appSecret   = $config['appSecret'];

    $this->connection  = new Oahu_Connection($config);


    if (isset($config['noHttpCache'])){
     $this->noHttpCache = $config['noHttpCache'];
    }

    if (isset($config['verbose'])){
      $this->verbose = (bool)$config['verbose'];
    } else {
      $this->verbose = false;
    }

    if (isset($config['debug']) && $config['debug']=='true') {
      $this->debug = (bool)$config['debug'];
      $this->debug_options = array();
    }
  }


  private static function parseConfig($config=array()) {
    // Oahu config
        
    foreach (self::$configKeys as $key) {
      $val = NULL;
      $envKey = "OAHU_" . strtoupper(Oahu_Utils::decamelize($key));

      if (isset($config[$key])) {
        $val = $config[$key];
      } elseif (getenv($envKey)) {
        $val = getenv($envKey);
      } elseif (isset(self::$defaultConfig[$key])) {
        $val = self::$defaultConfig[$key];
      }

      $config[$key] = $val;
    }
    return $config;
  }
  
  private function getSignedRequestCookieName() {
    return 'hsr_' . $this->appId;
  }

  private function getSignedAccount() {
    if (isset($_COOKIE[$this->getSignedRequestCookieName()])) {
      $hsr = json_decode(base64_decode($_COOKIE[$this->getSignedRequestCookieName()]));
      if ($hsr) {
        return get_object_vars($hsr);
      }
    } else {
      return false;
    }
  }

  // User account APIs
  public function validateUserAccount($account_object=null){

    if($account_object==null) {
      $account_object = $this->getSignedAccount();
    }

    if($account_object==null || $account_object == false){
      return false;
    }
    
    $at = $account_object['code'];
    $sd = $account_object['sig_date'];
    $ai = $account_object['_id'];
    $sc = $this->appSecret;
    
    $str = implode('-', array($at, $sd, $ai, $sc));

    if(md5($str)==$account_object['sig']){
      return $account_object['_id'];
    } else {
      return false;
    }
  }

   
  // Project API
  public function listProjects($params=array()) {
    return $this->_get("projects", $params);
  }
  public function getProject($projectId) {
    return $this->_get("projects/" . $projectId);
  }
  public function createProject($projectType, $projectData) {
    if (!in_array($projectType, self::$modelTypes["Project"])) {
      throw new Exception("ProjectType " . $projectType . " does not exist");
    }
    return $this->_post("projects", array(
      "_type"   => $projectType,
      "project" => self::_makeModel("Project", $projectData)
      )
    );
  }
  public function updateProject($projectId, $projectData) {
    return $this->_put("projects/" . $projectId, array(
      "project" => self::_makeModel("Project", $projectData)
    ));
  }
  public function updateProjectPoster($projectId, $imageId) {
    return $this->updateProject($projectId, array("default_image_id" => $imageId));
  }
  public function updateProjectTrailer($projectId, $videoId) {
    return $this->updateProject($projectId, array("default_video_id" => $videoId));
  }
  public function getProjectResources($projectId, $params=array()) {
    return $this->_get("projects/" . $projectId . "/resources", $params);
  }
  public function getProjectPhotos($projectId, $params=array()) {
    if (!$params["filters"]) {
      $params["filters"] = array();
    }
    $params["filters"]["type"] = "Resources::Image";
    return $this->getProjectResources($projectId, $params);
  }
  public function getProjectVideos($projectId, $params=array()) {
    if (!$params["filters"]) {
      $params["filters"] = array();
    }
    $params["filters"]["type"] = "Resources::Video";
    return $this->getProjectResources($projectId, $params);
  }
  public function listProjectPubAccounts($projectId) {
    return $this->listPubAccounts($projectId);
  }
  public function listProjectPublications($projectId, $params=array()) {
    return $this->_get("projects/" . $projectId . "/publications", $params);
  }



  //  Resources API
  public function getProjectResource($projectId, $resourceId) {
    return $this->_get("projects/" . $projectId . "/resources/" . $resourceId);
  }
  public function createProjectResource($projectId, $resourceType, $resourceData) {
    if (!in_array($resourceType, self::$modelTypes["Resource"])) {
      throw new Exception("ResourceType " . $resourceType . " does not exist");
    }
    return $this->_post("projects/". $projectId . '/resources', array(
      "_type"   => $resourceType,
      "resource" => self::_makeModel(self::_resourceModel($resourceType), $resourceData)
      )
    );
  }

  public function updateProjectResource($projectId, $resourceId, $resourceData) {
    $res = $this->getProjectResource($projectId, $resourceId);
    if (!$res) {
      throw new Exception("Resource " . $resourceId . " not found");
    }
    if ($res->can_edit) {
      $updateData = self::_makeModel(self::_resourceModel($res->_type), $resourceData, array("name", "description", "image_ids", "video_ids"));
      if (count($updateData) > 0) {
        return $this->_put("projects/" . $projectId . "/resources/" . $resourceId, array(
          "resource" => $updateData
        ));
      } else {
        return false;
      }
    } else {
      throw new Exception("The Resource " . $resourceId . " is not editable");
    }
  }
  public function createProjectImageList($projectId, $resourceData) {
    if (!$resourceData['image_ids']) {
      throw new Exception("You have to provide image_ids to create a ImageList Resource");
    }
    return $this->createProjectResource($projectId, "ImageList", $resourceData);
  }
  public function createProjectVideoList($projectId, $resourceData) {
    if (!$resourceData['video_ids']) {
      throw new Exception("You have to provide video_ids to create a VideoList Resource");
    }
    return $this->createProjectResource($projectId, "VideoList", $resourceData);
  }
  

  //Publications API
  public function getPubAccount($pubAccountId) {
    return $this->_get('pub_accounts/' . $pubAccountId);
  }
  public function listPubAccounts($projectId, $params=array()) {
    if (isset($projectId)) {
      return $this->_get('projects/' . $projectId . '/pub_accounts', $params);
    } else {
      return $this->_get('pub_accounts', $params);
    }
  }
  public function listPublications($pubAccountId, $params=array()) {
    return $this->_get('pub_accounts/' . $pubAccountId . "/publications", $params);
  }

  // App
  public function getApp($appId=null) {
    if (!$appId) {
      $appId = $this->appId;  
    }
    return $this->_get('apps/'.$appId);
  }

  // Lists API
  
  public function getList($listId) {
    return $this->_get('apps/' . $this->appId . '/lists/' . $listId);
  }

  public function getLists($filters) {
    return $this->_get('apps/' . $this->appId . '/lists', array('filters' => $filters));
  }

  public function createList($listData) {
    return $this->_post('apps/' . $this->appId . '/lists', $listData);
  }

  public function updateList($listId, $listData) {
    return $this->_put('apps/' . $this->appID . '/lists/' . $listId, $listData);
  }

  public function addListItem($listId, $itemId) {
    return $this->_post('apps/' . $this->appId . '/lists/' . $listId . '/add_item',  array("item_id" => $itemId));
  }

  public function removeListItem($listId, $itemId) {
    return $this->_post('apps/' . $this->appId . '/lists/' . $listId . '/remove_item',  array("item_id" => $itemId));
  }

  //Badges API
  public function listAchievements($params=array()) {
    return $this->_get('apps/'.$this->appId.'/achievements', $params);
  }

  public function getAchievement($achievementId) {
    if(!isset($achievementId)){
      return false;
    }
    return $this->_get('apps/'.$this->appId.'/achievements/'.$achievementId);
  }

  public function listBadges($achievementId, $params=array()){
    return $this->_get('apps/'.$this->appId.'/achievements/'.$achievementId.'/badges.json', $params);
  }

  public function unlockAchievement($actorId, $achievementId, $achievementData=array()){
    $achievement = $this->getAchievement($achievementId);
    if(!$achievementId || !$achievement || !$actorId){
      return false;
    }
    return $this->_put("apps/" . $this->appId . '/players/' . $actorId . '/achieve', array(
      'achievement_id' => $achievementId, 
      'data' => $achievementData
    ));
  }
  
  public function getAchievementSecret($achievementId=""){
    $actorId = $this->validateUserAccount();
    if (!$actorId) {
      return;
    }
    $time = time();
    $a = array(
      $time,
      $actorId,
      $achievementId,
      $this->appSecret
    );
    $key = implode('-', $a);
    return md5($key) . '-' . $time;
  }

  //Currency API

  public function listCurrencies(){
    return $this->_get('apps/'.$this->appId.'/currencies');
  }

  //TODO Currency Reward

  // public function rewardPlayer($appId, $playerId, $params) {
  //   return $this->_put('apps/' . $appId . "/players/" . $playerId . "/reward", $params);
  // }

  public function reward($rwd) {
    $actorId = $this->validateUserAccount();
    foreach ($rwd as $k=>$v) { $rwd[$k] = 0 + $v; }
    if ($actorId) {
      return $this->_put("apps/" . $this->appId . "/players/" . $actorId . "/reward", array("reward" => $rwd));
    } else {
      return $actorId;
    }
  }
  
  //Currencies

  //Leaderboards

  //Items / Goods

  public function createGood($goodData=array()) {
    return $this->_post('apps/' . $this->appId . '/goods', $goodData); #array('_type' => 'OahuGame::Good', 'good' => $params));
  }

  public function listGoods() {
    return $this->_get('apps/' . $this->appId . '/goods' );
  }

  // TODO Send Event serverside
  public function sendEvent($data=array(), $ctx=array()){
    return $this->_post('/events', array('data' => $data, 'ctx' => $ctx, 'meta' => array('time' => time())));
  }

  //Movie-specific methods    
  public function listMovies($params){
    return $this->listProjects($params);
  }
  public function getMovie($projectId) {
    return $this->getProject($projectId);
  }
  public function updateMovie($projectId, $projectData) {
    return $this->updateProject($projectId, $projectData);
  }
  public function updateMoviePoster($projectId, $imageId) {
    return $this->updateProjectPoster($projectId,$imageId);
  }
  public function updateMovieTrailer($projectId, $videoId) {
    return $this->updateProjectTrailer($projectId, $videoId);
  }
  public function createMovie($projectData) {
    return $this->createProject("Movie", $projectData);
  }
  public function getMovieResources($projectId, $params){
    return $this->getProjectResources($projectId, $params);
  }
  public function getMoviePhotos($projectId, $params){
    return $this->getProjectPhotos($projectId, $params);
  }
  public function getMovieVideos($projectId, $params){
    return $this->getProjectPhotos($projectId, $params);
  }
  public function listMoviePubAccounts($projectId) {
    return $this->listProjectPubAccounts($projectId);
  }
  public function listMoviePublications($projectId, $params){
    return $this->listProjectPublications($projectId, $params);
  }
  //Movies Resources API
  public function getMovieResource($projectId, $resourceId) {
    return $this->getProjectResource($projectId, $resourceId);
  }
  public function createMovieResource($projectId, $resourceType, $resourceData) {
    return $this->createProjectResource($projectId, $resourceType, $resourceData);
  }
  public function updateMovieResource($projectId, $resourceId, $resourceData) {
    return $this->updateProjectResource($projectId, $resourceId, $resourceData);
  }
  public function createMovieImageList($projectId, $resourceData) {
    return $this->createProjectImageList($projectId, $resourceData);
  }
  public function createMovieVideoList($projectId, $resourceData) {
    return $this->createProjectVideoList($projectId, $resourceData);
  }


  // Entities API
  
  public function listEntities($params=array()) {
    return $this->_get('apps/'.$this->appId.'/entities', $params);
  }

  public function getEntity($entityId) {
    if(!isset($entityId)){
      return false;
    }
    return $this->_get('apps/'.$this->appId.'/entities/'.$entityId);
  }

  public function createEntity($entityData) {
    return $this->_post('apps/'.$this->appId.'/entities/', $entityData);
  }

  public function updateEntity($entityId, $entityData) {
    return $this->_put('apps/'.$this->appId.'/entities/' . $entityId, $entityData);
  }

  // View Helpers
  public function imageUrl($id, $size="small") {
    return "//" . $this->host . "/img/" . $id . "/" . $size;
  }

  // Helpers
  private static function _resourceModel($resourceType) {
    if (in_array($resourceType, array_keys(self::$modelFields))) {
      return $resourceType;
    }
    else if (in_array("Resources::" . $resourceType, array_keys(self::$modelFields))) {
      return "Resources::" . $resourceType;
    } else {
      return "Resource";
    }
  }
  private static function _makeModel($modelType, $data=array(), $only=null) {
    $keys = array_intersect(self::$modelFields[$modelType], array_keys($data));
    if ($only) {
      $keys = array_intersect($keys, $only);
    }
    $model = array();
    foreach ($keys as $k) {
      if (array_key_exists($k, $data)) {
        if (is_string($data[$k])) {
          $model[$k] = stripslashes($data[$k]);
        } else {
          $model[$k] = $data[$k];
        }
      }
    }
    return $model;
  }


  // HTTP Plumbing...
  private function _get($path, $params=array(), $headers=array()) {
    $res = $this->connection->exec("GET", $path, $params, $headers);
    return $res['body'];
  }
  
  private function _post($path, $data, $headers=array()) {
    $res = $this->connection->exec("POST", $path, $data, $headers);
    return $res['body'];
  }
  
  private function _put($path, $data, $headers=array()) {
    $res =  $this->connection->exec("PUT", $path, $data, $headers);
    return $res['body'];
  }
  

}
