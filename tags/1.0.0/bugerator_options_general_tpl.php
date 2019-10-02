<!-- bugerator_options_general.php -->
<h1>Miscellaneous options:</h1>

<form action="<?PHP echo $page; ?>" method="post" name="options_general" >
    <input type="hidden" name="options_nonce" value="<?PHP echo $nonce; ?>" />
    <input type="hidden" name="bugerator_options_general" />

    <table class="bugerator bugerator_options_general" >
        <tr class="bugerator bugerator_options_general" >
            <td class="bugerator bugerator_options_general form_left" >
                Allow anonymous users to post issues?
            </td>
            <td class="bugerator bugerator_options_gerneral form_right" >
                <input type="checkbox" name="anonymous_post" 
                       <?PHP if ($options['anonymous_post'] == "true") echo "CHECKED" ?>  /> Yes if checked.
            </td>
        </tr>
        <tr class="bugerator bugerator_options_general" >
            <td class="bugerator bugerator_options_general form_left" >
                Allow uploading files?
            </td>
            <td class="bugerator bugerator_options_gerneral form_right" >
                <input type="checkbox" name="upload_files" 
                       <?PHP if ($options['upload_files'] == "true") echo "CHECKED" ?> /> Yes if checked.
            </td>
        </tr>        
        <tr class="bugerator bugerator_options_general" >
            <td class="bugerator bugerator_options_general form_left" >
		File size limit on uploaded files.
            </td>
            <td class="bugerator bugerator_options_gerneral form_right" >
                <input name="filesize" value="<?PHP echo $options['filesize'] ?>" /> in Bytes. 1048576(default) = 1MB, 2097152
		 = 2MB, 512000 = 500KB, etc.
            </td>
        </tr>
        <tr class="bugerator bugerator_options_general" >
            <td class="bugerator bugerator_options_general form_left" >
                Short date format: (see <a href="http://us3.php.net/manual/en/function.date.php"
                                           target="new" >this page</a> for options.)
            </td>
            <td class="bugerator bugerator_options_gerneral form_right" >
                <input name="date_format" value="<?PHP echo $options['date_format'] ?>" /> PHP date() format.
                Will print <b><?PHP echo date($options['date_format'],time()); ?></b>
            </td>
        </tr>        
        <tr class="bugerator bugerator_options_general" >
            <td class="bugerator bugerator_options_general form_left" >
                Long date format: (see <a href="http://us3.php.net/manual/en/function.date.php" 
                                          target="new" >this page</a> for options.)
            </td>
            <td class="bugerator bugerator_options_gerneral form_right" >
                <input name="long_date_format" value="<?PHP echo $options['long_date_format'] ?>" /> PHP date() format.
                Will print <b><?PHP echo date($options['long_date_format'],time()); ?></b>
            </td>
        </tr> 
        <tr class="bugerator bugerator_options_general" >
            <td class="bugerator bugerator_options_general form_left" >
		Default left/right page margins:
            </td>
            <td class="bugerator bugerator_options_gerneral form_right" >
                <input name="margin" value="<?PHP echo $options['margin'] ?>" /> ex. -50 will expand the bugerator page 
		by 50 pixels on each side.
            </td>
        </tr> 
        <tr class="bugerator bugerator_options_general" >
            <td class="bugerator bugerator_options_general" colspan="2" >
                <input type="submit" name="submit" value="Submit" class="button-primary" />
            </td>
        </tr>
    </table>
</form>
<h3>Other Options:</h3>
If you have edited the CSS and wan't to go back to the default:<br/>
<a href="<?PHP echo $page; ?>&tab=global&reset_css=yes&reset_nonce=<?PHP echo $nonce; ?>" >
    <input type="button" value="Click to reset css to default" class="button-primary" /></a>
<br/>
<br/>
<p>If you don't know how to take the Wordpress comments section off of a page:<br/>
<a href="<?PHP echo $page; ?>&tab=global&kill_comments=yes&kill_nonce=<?PHP echo $nonce; ?>" >
    <input type="button" value="Click to get rid of the Wordpress comments section" class="button-primary" /></a>
at the bottom of every page with the [bugerator] short code.<br/>
<p><h4>Note: Will only change the setting on the pages with the short code. This will not affect other pages.</h4>
