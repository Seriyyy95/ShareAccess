<h2>Записи с общим доступом:</h2>
<table>
<thead>
<th>Post ID</th>
<th>Запись</th>
<th>Пользователь</th>
<th>Действия</th>
</thead>
<tbody>
<?php foreach ($shared_posts as $shared_data): ?>
	<?php $post_title = get_the_title($shared_data["post_id"]) ?>
	<?php $user_info = get_userdata($shared_data["user_id"]);?>
	<?php $user_login = $user_info->user_login; ?>
	<tr>
		<td><?php echo $shared_data["post_id"]; ?></td>
		<td><?php echo $post_title; ?></td>
		<td><?php echo $user_login; ?></td>
		<td><a href="<?php echo admin_url( 'admin-post.php' ); ?>?action=shareaccess_unshare&id=<?php echo $shared_data["id"]; ?>">Удалить</a></td>
	</tr>
<?php endforeach; ?>
</tbody>
</table>
