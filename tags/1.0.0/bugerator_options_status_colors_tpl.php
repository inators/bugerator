<div class="issue_list_css">

    <h2>Choose Colors for the statuses</h2>
    <p>Any css color codes are valid. Ex: "Red" works the same as "#FF0000".
    <form name="bugerator_form" method="post" action="" >
        <input type="hidden" name="bugerator_options_nonce" value="<?PHP echo $nonce; ?>"/>
        <table style="text-align: center;">
            <th>Status</th>
            <th>Background Color</th>
            <th>Text color</th>
            <?PHP
            for ($x = 0; $x < count($statuses); $x++) {
                if (array_search($x, $statuses_used) !== false) {
                    ?> 
                    <tr>
                        <td  class="bugerator_option_colors"
                             style="background: <?PHP echo $status_colors[$x] ?>; 
                             color: <?PHP echo $status_text_colors[$x] ?>;" >

                            <?PHP echo $statuses[$x] ?>
                        </td>
                        <td>
                            <input name=color_choice["<?PHP echo $x; ?>"] size=8 value="<?PHP echo $status_colors[$x]; ?>">
                        </td>
                        <td>
                            <input name=text_color_choice["<?PHP echo$x; ?>"] size=8 value="<?PHP echo$status_text_colors[$x]; ?>">
                        </td>
                    </tr>
                <?PHP } else { ?>
                    <input type=hidden name=color_choice["<?PHP echo $x; ?>"] value=" <?PHP echo $status_colors[$x]; ?>" >
                    <input type=hidden name=text_color_choice"<?PHP echo $x; ?>"] value="<?PHP echo $status_colors[$x]; ?>">
                    <?PHP
                }
            }
            ?>
            <input type=hidden name="bugerator_color_form" value="yup">
            <tr><td colspan=3 style="text-align: left" >
                    <input type="submit" name="Submit" class="button-primary" value="Save Changes"/>
                </td>
            </tr>


        </table>
    </form>
    <form name="bugerator_color_reset" method="post" action="" >
        <input type="hidden" name="bugerator_options_nonce" value="<?PHP echo $nonce; ?>"/>
        <input type=hidden name="bugerator_reset_colors" value="yes please" >
        <input type=submit name=submit class="button-primary" value="Reset Colors to default" >
    </form>

</div><!--issue_list_css-->
