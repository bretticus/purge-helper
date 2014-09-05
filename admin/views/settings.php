<!-- Create a header in the default WordPress 'wrap' container -->
<div class="wrap">

    <!-- Add the icon to the page -->
    <div id="icon-options-general" class="icon-settings"></div>
    <h2>Purge Helper Settings</h2>

    <!-- Make a call to the WordPress function for rendering errors when settings are saved. -->
    <?php settings_errors(); ?>

    <!-- Create the form that will be used to render our options -->
    <form method="post" action="options.php">
        <?php settings_fields($namespace); ?>
        <?php do_settings_sections($namespace); ?>     
        <?php submit_button(); ?>
    </form>

</div><!-- /.wrap -->