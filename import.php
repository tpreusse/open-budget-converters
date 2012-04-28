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

$directorateDetails = array(
	'1000' => array(
		'name' => 'Gemeinde und Behörden',
		'acronym' => 'GuB'
	),
	'1100' => array(
		'name' => 'Präsidialdirektion',
		'acronym' => 'PRD'
	),
	'1200' => array(
		'name' => 'Direktion für Sicherheit, Umwelt und Energie',
		'acronym' => 'SUE'
	),
	'1300' => array(
		'name' => 'Direktion für Bildung, Soziales und Sport',
		'acronym' => 'BSS'
	),
	'1500' => array(
		'name' => 'Direktion für Tiefbau, Verkehr und Stadtgrün',
		'acronym' => 'TVS'
	),
	'1600' => array(
		'name' => 'Direktion für Finanzen, Personal und Informatik',
		'acronym' => 'FPI'
	),
	'2850' => array(
		'name' => 'Sonderrechnung Stadtentwässerung'
	),
	'2860' => array(
		'name' => 'Sonderrechnung Fonds für Boden- und Wohnbaupolitik'
	),
	'2870' => array(
		'name' => 'Sonderrechnung Entsorgung + Recycling'
	)
);

function clean_text($string) {
	$string = str_replace('^^^', ',', $string);
	$string = str_replace("\n", ' ', $string);
	$string = trim($string, '"*');
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
		if(isset($directorateDetails[$curDirectorate])) {
			$directorates[$curDirectorate]['name'] = $directorateDetails[$curDirectorate]['name'];
			if(isset($directorateDetails[$curDirectorate]['acronym'])) {
				$directorates[$curDirectorate]['acronym'] = $directorateDetails[$curDirectorate]['acronym'];
			}
		}
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

echo '<pre>';
echo json_encode($directorates);
echo '</pre>';

//file_put_contents('data/directorates.json', json_encode($directorates));

$flare = array('name' => 'Total');
$rootChilds = array();
$rootSpending = 0.0;
foreach($directorates as &$directorate) {
	$directorateChilds = array();
	$directorateSpending = 0.0;
	foreach($directorate['agencies'] as &$agency) {
		$agencyChilds = array();
		$agencySpending = 0.0;
		foreach($agency['product_groups'] as &$product_group) {
			if($product_group['budgets'][2012] > 0) {
				$agencyChilds[] = array(
					'name' => $product_group['name'],
					'type' => 'product_group',
					'spending' => number_format($product_group['budgets'][2012]),
					'size' => ceil($product_group['budgets'][2012])
				);
				$agencySpending += $product_group['budgets'][2012];
			}
		}
		if(!empty($agencyChilds)) {
			$directorateChild = array(
				'name' => $agency['name'],
				'type' => 'agency',
				'spending' => number_format($agencySpending)
			);
			if(count($agencyChilds) == 1 && $agencyChilds[0]['name'] == $agency['name']) {
				$directorateChild['size'] = ceil($agency['budgets'][2012]);
			}
			else {
				$directorateChild['children'] = $agencyChilds;
			}
			$directorateChilds[] = $directorateChild;
		}
		$directorateSpending += $agencySpending;
	}
	if(!empty($directorateChilds)) {
		$rootChild = array(
			'name' => $directorate['name'],
			'type' => 'directorate',
			'spending' => number_format($directorateSpending),
			'children' => $directorateChilds
		);
		if(isset($directorate['acronym'])) {
			$rootChild['acronym'] = $directorate['acronym'];
		}
		$rootChilds[] = $rootChild;
		$rootSpending += $directorateSpending;
	}
}
$flare['children'] = $rootChilds;
$flare['spending'] = number_format($rootSpending);

echo '<pre>';
echo json_encode($flare);
echo '</pre>';

file_put_contents('data/flare.json', json_encode($flare));

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