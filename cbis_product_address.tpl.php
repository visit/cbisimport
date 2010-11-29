<?php
// $Id$

/**
 * Writes out a adr microformat from CBIS address attributes
 */

$adr_props = array(
  'street-address' => 'StreetAddress1',
  'extended-address' => 'StreetAddress2',
  'postal-code' => 'postalCode',
  'locality' => 'cityAddress',
);
$address = '';
foreach ($adr_props as $key => $value) {
  if (!empty($attributes[$value])) {
    $address .= sprintf('<div class="%s">%s</div>', $key, check_plain($attributes[$value]));
  }
}

?>
<?php if($attributes['arena']) : ?>
  <div class="arena-name"><?php print $attributes['arena']; ?></div>
<?php endif; ?>

<?php if (!empty($address)): ?>
  <div class="adr">
    <?php print $address ?>
  </div>
<?php endif ?>