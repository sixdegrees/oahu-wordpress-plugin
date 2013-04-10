# Oahu Wordpress plugin

This plugin allows an easy integration of Oahu in your Wordpress blog.

## What does it do ?

* All Posts are automatically referenced in your Oahu App.
* OahuJS is automatically configured and initialized
* The Widgets and Templates defined in your Wordpress Theme are automatically loaded.


## Installation

The plugin in zip format can be downloaded [here](https://github.com/sixdegrees/oahu-wordpress-plugin/archive/master.zip)

Then head over to the "Install Plugins" page on your wp-admin : 

    open http://example.com/plugin-install.php?tab=upload

and upload the zip from there.

Alternatively, you can simply unzip it or clone the repo under `wp-content/plugins`.

## Setup

The admin Panel is under Settings > Oahu

## Creating and using Widgets in your Theme

You can create widgets in individual javascript files inside your Theme.


Theme structure:

    wp-content
    └── themes
        └── my_theme
            ├── home.php
            ├── index.php
            ├── oahu
            │   ├── templates
            │   │   └── my_widget.hbs
            │   └── widgets
            │       └── my_widget.js
            ├── page.php
            └── single.php

Example: 

**wp-content/themes/my-theme/oahu/widgets/my_widget.js**

    Oahu.Apps.register('my_widget', { 
      templates: ['my_widget']
    });

**wp-content/themes/my-theme/oahu/templates/my_widget.hbs**

    Hello from my widget

and then, to use this widget inside your views : 

    <?php oahu_widget('my_widget') ?>
    => <div data-oahu-widget='my_widget'></div>


### Widgets Helpers


**oahu_widget($name, $options=array(), $tagName = "div", $placeholder="")**

* `$name`: The widget's name
* `$options`: `array(key => val)` translated to `data-oahu-$key="$val"`
* `$tagName`: name of the wrapping tag
* `$placeholder`: Initial content placed inside your widget before first rendering


example

    <?php oahu_widget('identity', array('provider' => 'facebook')) >

**oahu_comments_widget($post_id, $options=array())**

* `$post_id`: the id of the Wordpress post you want to display the comments for.
* `$options`: same as `oahu_widget`


example

    <?php oahu_comments_widget($post->ID) ?>

**oahu_reviews_widget($post_id, $options=array())**

* `$post_id`: the id of the Wordpress post you want to display the reviews for.
* `$options`: same as `oahu_widget`


example

    <?php oahu_reviews_widget($post->ID) ?>
