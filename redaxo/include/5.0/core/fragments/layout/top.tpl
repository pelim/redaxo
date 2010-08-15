<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo $this->i18n('htmllang'); ?>" lang="<?php echo $this->i18n('htmllang'); ?>">
<head>
  <title><?php echo $this->pageTitle ?></title>
  <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $this->i18n('htmlcharset'); ?>" />
  <meta http-equiv="Content-Language" content="<?php echo $this->i18n('htmllang'); ?>" />
  <link rel="stylesheet" type="text/css" href="../redaxo_media/css_import.css" media="screen, projection, print" />
  <!--[if lte IE 7]>
    <link rel="stylesheet" href="../redaxo_media/css_ie_lte_7.css" type="text/css" media="screen, projection, print" />
  <![endif]-->
      
  <!--[if IE 7]>
    <link rel="stylesheet" href="../redaxo_media/css_ie_7.css" type="text/css" media="screen, projection, print" />
  <![endif]-->
  
  <!--[if lte IE 6]>
    <link rel="stylesheet" href="../redaxo_media/css_ie_lte_6.css" type="text/css" media="screen, projection, print" />
  <![endif]-->

  <!-- jQuery immer nach den Stylesheets! -->
  <script src="../redaxo_media/jquery.min.js" type="text/javascript"></script>
  <script src="../redaxo_media/standard.js" type="text/javascript"></script>
  <script type="text/javascript">
  <!--
  var redaxo = true;

  // jQuery is now removed from the $ namespace
  // to use the $ shorthand, use (function($){ ... })(jQuery);
  // and for the onload handler: jQuery(function($){ ... });
  jQuery.noConflict();
  //-->
  </script>
  
  <?php echo $this->pageHeader ?>
</head>
<body <?php echo $this->bodyAttr; ?>>
<div id="rex-website">
  <div id="rex-header">
    <p class="rex-header-top"><a href="../index.php" onclick="window.open(this.href);"><?php echo $this->serverName ?></a></p>
  </div>

  <div id="rex-navi-logout"><?php echo $this->logout ?></div>
  <div id="rex-navi-main"><?php echo $this->navigation ?></div>

  <div id="rex-wrapper">
    <div id="rex-wrapper2">