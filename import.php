<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	</head>
	<body>
<?php

$directorates = array();

$overviewFile = file_get_contents('data/source/overview.csv');
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
			'net_cost' => array(
				'budgets' => array(
					2012 => convert_to_float(clean_text($row[5])),
					2011 => convert_to_float(clean_text($row[6]))
				),
				'accounts' => array(
					2010 => convert_to_float(clean_text($row[7]))
				)
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
			'net_cost' => array(
				'budgets' => array(
					2012 => convert_to_float(clean_text($row[5])),
					2011 => convert_to_float(clean_text($row[6]))
				),
				'accounts' => array(
					2010 => convert_to_float(clean_text($row[7]))
				)
			),
			'product_groups' => array()
		);
		$curAgency = $row[2];
	}
	else if(!is_null($curAgency) && preg_match('/^PG[0-9]{6}$/', $row[3])) {
		//ToDo: verify with city of bern that this is a valid correction
		if($row[3] == 'PG130000') {
			$row[3] = 'PG130100';
		}
		$directorates[$curDirectorate]['agencies'][$curAgency]['product_groups'][$row[3]] = array(
			'number' => $row[3],
			'name' => clean_text($row[4]),
			'net_cost' => array(
				'budgets' => array(
					2012 => convert_to_float(clean_text($row[5])),
					2011 => convert_to_float(clean_text($row[6]))
				),
				'accounts' => array(
					2010 => convert_to_float(clean_text($row[7]))
				)
			),
			'products' => array()
		);
	}
}

$productCostRowProcessor = function($row) {
	return array(
		'name' => clean_text($row[1]),
		'net_cost' => array(
			'budgets' => array(
				2012 => convert_to_float(clean_text($row[8])),
				2011 => convert_to_float(clean_text($row[10]))
			)
		),
		'gross_cost' => array(
			'budgets' => array(
				2012 => convert_to_float(clean_text($row[2]))
			)
		),
		'revenue' => array(
			'budgets' => array(
				2012 => convert_to_float(clean_text($row[5]))
			)
		)
	);
};
$productRevenueRowProcessor = function($row) use ($productCostRowProcessor) {
	$result = $productCostRowProcessor($row);
	$result['net_cost']['budgets'][2012] *= -1;
	$result['net_cost']['budgets'][2011] *= -1;
	return $result;
};

foreach($directorates as &$directorate) {
	$directorateFileName = 'data/source/'.$directorate['number'].'.csv';
	if(!file_exists($directorateFileName)) {
		continue;
	}
	$directorateFile = file_get_contents($directorateFileName);
	preg_match_all('/\n([^,]*,){11}.*/', $directorateFile, $directorateRows);
	
	$productRowProcessor = NULL;
	
	foreach($directorateRows[0] as $rowNumber => &$row) {
		$row = explode(',', $row);
		$col0 = trim($row[0]);
		$isProductRow = preg_match('/^P[0-9]{6}$/', $col0);
		if($isProductRow) {
			$agencyNumber = substr($col0, 1, 2).'0';
			if(!isset($directorate['agencies'][$agencyNumber])) {
				echo 'WARNING: Agency "'.$agencyNumber.'" not found (Product #'.$col0.').<br />'.PHP_EOL;
				continue;
			}
			$agency = &$directorate['agencies'][$agencyNumber];
			if($col0 == 'P130210') {
				$productGroupNumber = 'PG130100';
			}
			else {
				//ToDo: verify that this is a correct assumtion
				$productGroupNumber = 'PG'.substr($col0, 1, 4).'00';
			}
			if(!isset($agency['product_groups'][$productGroupNumber])) {
				echo 'WARNING: Product group "'.$productGroupNumber.'" not found (Product #'.$col0.').<br />'.PHP_EOL;
				continue;
			}
			if($productRowProcessor !== NULL) {
				$productGroup = &$agency['product_groups'][$productGroupNumber];
				$productGroup['products'][$col0] = $productRowProcessor($row);
			}
			else {
				echo 'WARNING: Found product "'.$col0.'" but missing a processor function.<br />'.PHP_EOL;
			}
		}
		else if(
			$col0 == 'Nummer' && 
			$row[1] == 'Produkt' && 
			$row[2] == 'Bruttokosten 2012' && 
			$row[5] == 'Erlös 2012' && 
			$row[8] == 'Nettokosten' && 
			$row[10] == 'Nettokosten' && 
			trim($row[11]) == 'Abweichung'
		) {
			
			if(isset($directorateRows[0][$rowNumber+1])) {
				$nextRow = $directorateRows[0][$rowNumber+1];
				$nextRow = explode(',', $nextRow);
				if(
					$nextRow[2] == 'Fr.' &&
					$nextRow[5] == 'Fr.' &&
					$nextRow[8] == '2012 / Fr.' &&
					$nextRow[10] == '2011 / Fr.' &&
					trim($nextRow[11]) == '2012/2011 %'
				) {
					$productRowProcessor = $productCostRowProcessor;
				}
				
			}
		}
		else if(
			$col0 == 'Nummer' && 
			$row[1] == 'Produkt' && 
			$row[2] == 'Bruttokosten 2012' && 
			$row[5] == 'Erlös 2012' && 
			$row[8] == 'Nettoerlös' && 
			$row[10] == 'Nettoerlös' && 
			trim($row[11]) == 'Abweichung'
		) {
			
			if(isset($directorateRows[0][$rowNumber+1])) {
				$nextRow = $directorateRows[0][$rowNumber+1];
				$nextRow = explode(',', $nextRow);
				if(
					$nextRow[2] == 'Fr.' &&
					$nextRow[5] == 'Fr.' &&
					$nextRow[8] == '2012 / Fr.' &&
					$nextRow[10] == '2011 / Fr.' &&
					trim($nextRow[11]) == '2012/2011 %'
				) {
					$productRowProcessor = $productRevenueRowProcessor;
				}
				
			}
		}
		else if(!empty($col0) && !$isProductRow) {
			$productRowProcessor = NULL;
		}
	}
}

echo '<pre>';
var_dump($directorates);
echo '</pre>';

exit;

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
					'size' => ceil($product_group['budgets'][2012])
				);
				$agencySpending += $product_group['budgets'][2012];
			}
		}
		if(!empty($agencyChilds)) {
			$directorateChild = array(
				'name' => $agency['name'],
				'type' => 'agency',
				'size' => ceil($agencySpending)
			);
			if(!(count($agencyChilds) == 1 && $agencyChilds[0]['name'] == $agency['name'])) {
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
			'size' => ceil($directorateSpending),
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
$flare['size'] = ceil($rootSpending);

echo '<pre>';
echo json_encode($flare);
echo '</pre>';

//file_put_contents('data/flare.json', json_encode($flare));

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