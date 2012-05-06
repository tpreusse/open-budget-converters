<?php

if(empty($_POST['g'])) {
	$_POST['g'] = '';
}

header("Content-Disposition: attachment; filename=bernbudget2012.svg");
header("Content-Type: image/svg+xml");

?>
<svg xmlns="http://www.w3.org/2000/svg">
	<style type="text/css">
		text {
			font-size: 11px;
			pointer-events: none;
		}
		
		text.product_group {
			font-weight:bold;
		}
		
		text.agency, text.directorate {
			fill: #e1353f;
			text-shadow:0 0 20px #fff;
			font-weight:bold;
			font-size:15px;
			dy:.35em;
		}
		
		text.directorate {
			fill: #a8272d;
			font-size:20px;
		}
		
		circle {
			fill: #e1353f;
			fill-opacity: .1;
			stroke: #e1353f;
			pointer-events: all;
		}
		
		circle.parent {
			fill: #a8272d;
			fill-opacity: .1;
			stroke: #a8272d;
		}
	</style>
	<?php echo $_POST['g']; ?>
</svg>