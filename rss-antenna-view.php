<div class='rss-antenna'>
	<?php foreach($info->items as $item): ?>
	<div class='rss-item'>
	<a href="<?php echo $item->url; ?>" target="_blank">
		<p class='title'>
			<?php echo $item->title; ?>
		</p>
		<p class='info'>
			<span class='sitename'>[<?php echo $item->site_name; ?>] <?php echo $item->date; ?></span>
		</p>
		<?php if( !empty($item->description) && !empty($item->img_src) ): ?>
			<p class='description <?php echo $info->description_position ?>'>
				<?php echo $item->description; ?>
			</p>
			<img class='<?php echo $info->image_position;?>' src='<?php echo $item->img_src;?>'  alt=''>
		<?php else: ?>
			<p class='description-only'>
				<?php echo $item->description; ?>
			</p>
		<?php endif; ?>
		<hr>
	</a>
	</div>
	<?php endforeach; ?>
</div>
