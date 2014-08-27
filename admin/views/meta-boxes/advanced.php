<p><?php _e( 'You have chosen to clone the main blog. Please <strong>select tables you want cloned</strong>. Beware, network-only tables can take up a lot of space and a long time to clone.', WPMUDEV_CLONER_LANG_DOMAIN ); ?></p>
<div id="additional-tables" style="display:none">
	<select id="additional-tables-selector">
		<?php foreach ( $additional_tables as $table ): ?>
			<?php
                $table_name = $table['name'];
                $value = $table['prefix.name'];
            ?>
            <option value="<?php echo $value; ?>"><?php echo $table_name; ?></option>
		<?php endforeach; ?>
	</select>
</div>
<ul id="additional-tables-checkboxes">
	<?php $i = 0; ?>
	<?php foreach ( $additional_tables as $table ): ?>
		<?php if ( $i % 3 == 0 ): ?>
			<br class="clear"/>
		<?php endif; ?>

		<?php
            $table_name = $table['name'];
            $value = $table['prefix.name'];

            $checked = in_array( $value, $additional_tables_previous_selection );
        ?>
        <li>
        	<input type="checkbox" name="additional_tables[]" id="table-<?php echo $value; ?>" <?php checked( $checked ); ?> value="<?php echo $value; ?>" />
        	<label for="table-<?php echo $value; ?>"><?php echo $table_name; ?></label><br/>
        </li>
		<?php $i++; ?>
	<?php endforeach; ?>
</ul>
<div class="clear"></div>

	