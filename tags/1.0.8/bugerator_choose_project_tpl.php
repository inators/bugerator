<!-- bugerator_choose_project.php -->
<div class="choose_project" id="choose">
    <h2 class="choose_project" >
	Please choose which project you would like to <?PHP echo $source ?></h2><br/><br/>
	<table class="choose_project">
	    <tr class="choose_project">
		<th class="choose_project">
		    Project Name
		</th>
		<th class="choose_project">
		    Status
		</th>
		<th class="choose_project">
		    Current Version
		</th>
		<th class="choose_project">
		    Next Version
		</th>
		<th class="choose_project">
		    Next Release Date
		</th>
	    </tr>
	    <?PHP
	    for ($x=0;$x<count($output_keys);$x++) {
	    ?>
	    <tr class="choose_project">
		<td class="choose_project">
		    <a class="choose_project" href="<?PHP echo $post->guid?>&bugerator_nav=<?PHP 
		    echo $my_source; ?>&bug_project=<?PHP 
		    echo $output_results[$output_keys[$x]]->id ?>" ><?PHP echo $output_results[$output_keys[$x]]->name ;?></a>
		</td>
		<td class="choose_project">
		    <?PHP echo $project_statuses[$output_results[$output_keys[$x]]->status]; ?>
		</td>
		<td class="choose_project">
		    <?PHP echo $output_results[$output_keys[$x]]->thisversion; ?>
		</td>
		<td class="choose_project">
		    <?PHP echo $output_results[$output_keys[$x]]->next_version; ?>
		</td>
		<td class="choose_project">
		    <?PHP echo $output_results[$output_keys[$x]]->next_date; ?>
		</td>
	    </tr>
	    <?PHP } ?>
	</table>
    </h2>
</div><!-- choose -->