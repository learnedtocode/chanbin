<?php

switch ($route_params['list_type']) {
	case 'recent':
		$pastes = Paste::list_recent();
		$display_list_type = '';
		break;
	case 'uid':
		$pastes = Paste::list_by_ip_hash($route_params['list_ip_hash']);
		$uid = run_hooks('ip_hash_to_display', $route_params['list_ip_hash']);
		$display_list_type = 'for UID: ' . $uid;
		break;
	case 'trip':
		$pastes = Paste::list_by_trip($route_params['list_trip']);
		$display_list_type = 'for tripcode: !!!' . $route_params['list_trip'];
		break;
}
if (!count($pastes)) {
	fail(404, htmlspecialchars(trim('No pastes ' . $display_list_type)));
}

$display_list_title = 'Recent pastes ' . $display_list_type;
page_header($display_list_title, ['body_class' => 'list-pastes']);
?>
<div id="page-text">
	<h2><?php echo htmlspecialchars($display_list_title); ?></h2>
	<table class="paste-list">
		<tr class="header">
			<th class="cell-index"></th>
			<th class="cell-date">Date</th>
			<th class="cell-title">Title</th>
			<th class="cell-user">User/Trip</th>
			<th class="cell-uid">UID</th>
			<th class="cell-size">Size</th>
		</tr>
<?php foreach ($pastes as $i => $paste) {
	$a = '<a href="/paste/' . htmlspecialchars($paste->id) . '">';
?>
		<tr class="paste-info <?php echo $i % 2 ? 'even' : 'odd'; ?>">
			<td class="cell-index"><?php echo htmlspecialchars($i + 1); ?></td>
			<td class="cell-date"><?php echo $a . $paste->getDateHTML(); ?></a></td>
			<td class="cell-title"><?php echo $a . $paste->getTitleHTML(); ?></a></td>
			<td class="cell-user"><?php echo $paste->getUserTripHTML(true); ?></td>
			<td class="cell-uid"><?php echo $paste->getUIDHTML(); ?></td>
			<td class="cell-size"><?php echo $paste->getSizeHTML(); ?></td>
		</tr>
<?php } ?>
	</table>
</div>
<?php
page_footer();
