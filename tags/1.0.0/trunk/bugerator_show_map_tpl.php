<table class="bugerator bugerator_map" >
    <?PHP for($x=(count($version_list)-1);$x>-1;$x--): ?>
    <tr class="bugerator bugerator_map" >
	<th class="bugerator bugerator_map" >
	    Version: <?PHP echo $version_list[$x]; ?>
	</th>
	<th class="bugerator bugerator_map" colspan ="2" >
	    <?PHP echo $project_info->name; ?>
	</th>
	<th class="bugerator bugerator_map" colspan ="2">
	    Anticipated release date: <?PHP echo $goal_list[$x]; ?>
	</th>
    </tr>
</table>
<table class="bugerator bugerator_map" >
    <?PHP
    for ($i=0;$i<count($results);$i++):
	if ($results[$i]->version == $x):?>
    <tr class="bugerator bugerator_map" style="<?PHP
	    if ($results[$i]->status == "5" or
		    $results[$i]->status == "6" or
		    $results[$i]->status == "7" or
		    $results[$i]->status == "8" or
		    $results[$i]->status == "9" or
		    $results[$i]->status == "10")
		 echo "text-decoration: line-through; ";
	echo $style[$results[$i]->status]; ?>" >
	<td class="bugerator bugerator_map" >
	    <?PHP echo $results[$i]->id; ?>
	</td>
	<td class="bugerator bugerator_map" >
	    <?PHP echo $types[$results[$i]->type]; ?>
	</td>
	<td class="bugerator bugerator_map" >
	    <a class='bugerator_issue_link' href='<?PHP
	    echo $post->guid;?>&bugerator_nav=display&project=<?PHP echo $project;?>&issue=<?PHP 
	    echo $results[$i]->id;?>'><?PHP echo stripslashes($results[$i]->title); ?></a>
	</td>
	<td class="bugerator bugerator_map" >
	    <?PHP echo $statuses[$results[$i]->status]; ?>
	</td>
	<td class="bugerator bugerator_map" >
	    <?PHP echo $results[$i]->priority; ?>
	</td>
    </tr>
    <?PHP endif; endfor; endfor; ?>
</table>
    