<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	</head>
	<body>
<?php

$directorates = array();

$overviewFile = file_get_contents('overview-utf8.csv');
preg_match_all('/\n([^,]*,){9}.*/', $overviewFile, $overviewRows);

$curDirectorate = NULL;
$curAgency = NULL;

function clean_text($string) {
	$string = str_replace('^^^', ',', $string);
	$string = trim($string, '"');
	return $string;
}

function convert_to_float($number) {
	$number = str_replace(',', '', $number);
	return (float)$number;
}

foreach($overviewRows[0] as &$row) {
	$row = explode(',', $row);
	if(preg_match('/^[0-9]{4}$/', $row[1])) {
		$directorates[$row[1]] = array(
			'number' => $row[1],
			'name' => clean_text($row[2]),
			'budgets' => array(
				2012 => convert_to_float(clean_text($row[5])),
				2011 => convert_to_float(clean_text($row[6]))
			),
			'bills' => array(
				2010 => convert_to_float(clean_text($row[7]))
			),
			'agencies' => array()
		);
		$curDirectorate = $row[1];
		$curAgency = NULL;
	}
	else if(!is_null($curDirectorate) && preg_match('/^[0-9]{3}$/', $row[2])) {
		$directorates[$curDirectorate]['agencies'][$row[2]] = array(
			'number' => $row[2],
			'name' => clean_text($row[3]),
			'budgets' => array(
				2012 => convert_to_float(clean_text($row[5])),
				2011 => convert_to_float(clean_text($row[6]))
			),
			'bills' => array(
				2010 => convert_to_float(clean_text($row[7]))
			),
			'product_groups' => array()
		);
		$curAgency = $row[2];
	}
	else if(!is_null($curAgency) && preg_match('/^PG[0-9]{6}$/', $row[3])) {
		$directorates[$curDirectorate]['agencies'][$curAgency]['product_groups'][$row[3]] = array(
			'number' => $row[3],
			'name' => clean_text($row[4]),
			'budgets' => array(
				2012 => convert_to_float(clean_text($row[5])),
				2011 => convert_to_float(clean_text($row[6]))
			),
			'bills' => array(
				2010 => convert_to_float(clean_text($row[7]))
			)
		);
	}
}
//$overview = explode(',', $overview);

echo '<pre>';
var_dump($directorates);
echo '</pre>';

//echo json_encode($directorates);

$flare = array('name' => ' ');
$rootChilds = array();
foreach($directorates as &$directorate) {
	$directorateChilds = array();
	foreach($directorate['agencies'] as &$agency) {
		$agencyChilds = array();
		foreach($agency['product_groups'] as &$product_group) {
			if($product_group['budgets'][2012] > 0) {
				$agencyChilds[] = array(
					'name' => '', //$product_group['name'],
					'size' => ceil($product_group['budgets'][2012])
				);
			}
		}
		if(!empty($agencyChilds)) {
			$directorateChilds[] = array(
				'name' => '', //$agency['name'],
				'children' => $agencyChilds
			);
		}
	}
	if(!empty($directorateChilds)) {
		$rootChilds[] = array(
			'name' => $directorate['name'],
			'children' => $directorateChilds
		);
	}
}
$flare['children'] = $rootChilds;

//echo json_encode($flare);

//CSV for openspending (not working yet)
$csv = 'nummer;direktion;dienststelle;produktgruppe;date;budget'."\n";
foreach($directorates as &$directorate) {
	foreach($directorate['agencies'] as &$agency) {
		foreach($agency['product_groups'] as &$product_group) {
			if($product_group['budgets'][2012] > 0) {
				$csv .= $product_group['number'].';'.$directorate['name'].';'.$agency['name'].';'.$product_group['name'].';2012;'.ceil($product_group['budgets'][2012])."\n";
			}
		}
	}
}
//file_put_contents('data/stadtbernbudget2012.csv', $csv);

?>
	</body>
</html>