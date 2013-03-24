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
			fill: #fff;
			text-shadow:0 0 10px #000;
			font-weight:bold;
			font-size:15px;
			dy:.35em;
		}
		
		text.directorate {
			font-size:25px;
		}
		
		circle.directorate {
			stroke-width: 3px;
		}
		
		circle.directorate.GuB {
			stroke:gray;
			fill:gray;
		}
		circle.directorate.FPI {
			stroke:#074EA1;
			fill:#074EA1;
		}
		circle.directorate.PRD, circle.directorate.BSS {
			stroke:#E11F21;
			fill:#E11F21;
		}
		circle.directorate.SUE {
			stroke:#F07D08;
			fill:#F07D08;
		}
		circle.directorate.TVS {
			stroke:#060;
			fill:#060;
		}
		
		circle {
			fill:#000;
			fill-opacity: .05;
			stroke: #fff;
			stroke-width: 1px;
			pointer-events: all;
		}
		
		circle.parent {
			stroke: #fff;
		}
	</style>
	<?php echo $_POST['g']; ?>
</svg>