<div class='rss-antenna'>
	<?php foreach($info->items as $item): ?>
	<p class='title'>
		<a href="<?php echo $item->url; ?>" target="_blank"><?php echo $item->title; ?>
		</a>
	</p>
	<p class='info'>
		<span class='sitename'>[<?php echo $item->site_name; ?>]
		</span><span class='date'><?php echo $item->date; ?> </span>
	</p>

	<?php if( empty($item->description) ): ?>
	<p class='description-only'>
		<?php echo $item->description; ?>
	</p>

	<?php elseif (!empty($item->img_tag)): ?>
	<p class='description'>
		<?php echo $item->description; ?>
		<a href="<?php echo $item->url; ?>" target="_blank"> 続きを読む</a>
	</p>
	<?php echo $item->img_tag; ?>

	<?php else: ?>
	<p class='description-only'>
		<?php echo $item->description; ?>
		<a href="<?php echo $item->url; ?>" target="_blank"> 続きを読む</a>
	</p>
	<?php endif; ?>
	<hr>
	<?php endforeach; ?>
</div>
