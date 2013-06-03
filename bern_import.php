<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	</head>
	<body>
<?php

$directorates = array();

$year = 2013;

$overviewFile = file_get_contents('source/bern/'.$year.'/csv/overview.csv');
preg_match_all('/\n(("[^"]*"|[^,]*),){9}.*/', $overviewFile, $overviewRows);

$curDirectorate = NULL;
$curAgency = NULL;

$directorateDetails = array(
	'1000' => array(
		'name' => "Gemeinde und Behörden",
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

$directorateDisplayName = array(
	'1000' => 'Gemeinde und '.PHP_EOL.'Behörden',
	'1100' => 'Präsidialdirektion',
	'1200' => 'Sicherheit, '.PHP_EOL.'Umwelt und Energie',
	'1300' => 'Bildung, '.PHP_EOL.'Soziales und Sport',
	'1500' => 'Tiefbau, Verkehr '.PHP_EOL.'und Stadtgrün',
	'1600' => 'Finanzen, Personal '.PHP_EOL.'und Informatik',
	'2850' => 'Sonderrechnung Stadtentwässerung',
	'2860' => 'Sonderrechnung Fonds für Boden- und Wohnbaupolitik',
	'2870' => 'Sonderrechnung Entsorgung + Recycling'
);

function clean_text($string) {
	$string = str_replace('^^^', ',', $string);
	$string = str_replace("\n", ' ', $string);
	$string = trim($string, '"*');
	return $string;
}

function convert_to_float($number) {
	$number = str_replace(',', '', clean_text($number));
	return round((float)$number, 2);
}

foreach($overviewRows[0] as &$rowText) {
	$row = str_getcsv($rowText, ',', '"');
	//var_dump($row);
	#var_dump($row[0]);
	$row[0] = trim($row[0]);
	if(preg_match('/^[0-9]{4}$/', $row[0])) {
		$directorates[$row[0]] = array(
			'number' => $row[0],
			'name' => clean_text($row[1]),
			'net_cost' => array(
				'budgets' => array(
					$year => convert_to_float($row[4]),
					$year-1 => convert_to_float($row[5])
				),
				'accounts' => array(
					$year-2 => convert_to_float($row[6])
				)
			),
			'agencies' => array()
		);
		$curDirectorate = $row[0];
		$curAgency = NULL;
		if(isset($directorateDetails[$curDirectorate])) {
			$directorates[$curDirectorate]['name'] = $directorateDetails[$curDirectorate]['name'];
			if(isset($directorateDetails[$curDirectorate]['acronym'])) {
				$directorates[$curDirectorate]['acronym'] = $directorateDetails[$curDirectorate]['acronym'];
			}
		}
	}
	else if(!is_null($curDirectorate) && preg_match('/^[0-9]{3}$/', $row[1])) {
		$row[1] = (string)$row[1];
		$directorates[$curDirectorate]['agencies'][$row[1]] = array(
			'number' => $row[1],
			'name' => clean_text($row[2]),
			'net_cost' => array(
				'budgets' => array(
					$year => convert_to_float($row[4]),
					$year-1 => convert_to_float($row[5])
				),
				'accounts' => array(
					$year-2 => convert_to_float($row[6])
				)
			),
			'product_groups' => array()
		);
		$curAgency = $row[1];
	}
	else if(!is_null($curAgency) && preg_match('/^PG[0-9]{6}$/', $row[2])) {
		//TODO: verify with city of bern that this is a valid correction
		if($row[2] == 'PG130000') {
			$row[2] = 'PG130100';
		}
		$directorates[$curDirectorate]['agencies'][$curAgency]['product_groups'][$row[2]] = array(
			'number' => $row[2],
			'name' => clean_text($row[3]),
			'net_cost' => array(
				'budgets' => array(
					$year => convert_to_float($row[4]),
					$year-1 => convert_to_float($row[5])
				),
				'accounts' => array(
					$year-2 => convert_to_float($row[6])
				)
			),
			'products' => array()
		);
	}
}

$productCostRowProcessor = function($row) {
	global $year;
	return array(
		'name' => clean_text($row[1]),
		'net_cost' => array(
			'budgets' => array(
				$year => convert_to_float($row[8]),
				$year-1 => convert_to_float($row[10])
			)
		),
		'gross_cost' => array(
			'budgets' => array(
				$year => convert_to_float($row[2])
			)
		),
		'revenue' => array(
			'budgets' => array(
				$year => convert_to_float($row[5])
			)
		)
	);
};
$productRevenueRowProcessor = function($row) use ($productCostRowProcessor) {
	global $year;
	$result = $productCostRowProcessor($row);
	$result['net_cost']['budgets'][$year] *= -1;
	$result['net_cost']['budgets'][$year-1] *= -1;
	return $result;
};

define('DIRECTORATE', 1);
define('AGENCY', 2);
define('PRODUCT_GROUP', 3);

define('DIRECTORATE_TABLE', 1);
//define('DIRECTORATE_AGENCY_TABLE', 2);
define('AGENCY_TABLE', 2);
define('PRODUCT_GROUP_TABLE', 3);
define('PRODUCT_TABLE', 4);
define('PRODUCT_REVENUE_TABLE', 5);

$null = NULL;

foreach($directorates as &$directorate) {
	$directorateFileName = 'source/bern/'.$year.'/csv/'.$directorate['number'].'.csv';
	if(!file_exists($directorateFileName)/* || $directorate['number'] != '1200'*/) {
		continue;
	}

	$directorateFile = file_get_contents($directorateFileName);
	preg_match_all('/\n(("[^"]*"|[^,]*),){11}.*/', $directorateFile, $directorateRows);
	
	$sectionType = $null;
	$tableType = $null;
	$agency = &$null;
	
	$directorateRowCount = count($directorateRows[0])-1;
	//echo 'count '.$directorateRowCount.'<br />';
	
	foreach($directorateRows[0] as $rowNumber => &$rowText) {
		//echo $rowNumber.' ';
		$row = str_getcsv($rowText, ',', '"');
		
		$col0 = trim($row[0]);
		$rowType = NULL;
		
		$rowText = trim($rowText);
		if($rowText == ',,,,,,,,,,,') {
			continue;
		}
		
		if(preg_match('/^[0-9]{4}$/', $col0) && clean_text(trim($row[1])) == ($directorate['name'].(isset($directorate['acronym']) ? ' ('.$directorate['acronym'].')' : ''))) {
			$sectionType = DIRECTORATE;
			#echo 'section: DIRECTORATE '.$rowText.'<br />'.PHP_EOL;
			continue;
		}
		else if(preg_match('/^([0-9]{3})(-[0-9]{3})?,("[^"]*"|[^,]*),,,,,,,,,,$/', $rowText, $agencyMatches)) {
			$sectionType = AGENCY;
			$tableType = NULL;
			if(!isset($directorate['agencies'][$agencyMatches[1]])) {
				echo 'WARNING: Agency "'.$agencyMatches[1].'" not found.<br />'.PHP_EOL;
			}
			else {
				$agency = &$directorate['agencies'][$agencyMatches[1]];
			}
			#echo 'section: AGENCY '.$rowText.'<br />'.PHP_EOL;
			continue;
		}
		else if(preg_match('/^,"?Produktegruppe (PG[0-9]{6}) ([^,]+|[^"]+"),,,,,,,,,,$/', $rowText, $productGroupMatches)) {
			$sectionType = PRODUCT_GROUP;
			$tableType = NULL;
			if(!isset($agency['product_groups'][$productGroupMatches[1]])) {
				echo 'WARNING: Product group "'.$productGroupMatches[1].'" not found.<br />'.PHP_EOL;
			}
			else {
				$productGroup = &$agency['product_groups'][$productGroupMatches[1]];
			}
			#echo 'section: PRODUCT_GROUP '.$rowText.'<br />'.PHP_EOL;
			continue;
		}
		
		if($rowNumber < $directorateRowCount) {
			$nextRow = trim($directorateRows[0][$rowNumber+1]);
		}
		else {
			$nextRow = '';
		}
		
		switch($sectionType) {
			case DIRECTORATE:
				if (
					$rowText == 'Kosten und,,,Voranschlag,,Voranschlag,Rechnung,,Rechnung,,,'
					&& $nextRow == 'Erlöse,,,'.$year.',,'.($year-1).','.($year-2).',,'.($year-3).',,,'
				) {
					$tableType = DIRECTORATE_TABLE;
					#echo 'table: DIRECTORATE_TABLE'.'<br />'.PHP_EOL;
				}
				/*else if (
					$rowText == 'Nummer,Dienststelle,Bruttokosten '.$year.',,,Erlös '.$year.',,,Nettokosten,,Nettokosten,Abweichung'
					&& $nextRow == ',,Fr.,%,,Fr.,%,,'.$year.' / Fr.,,'.($year-1).' / Fr.,'.$year.'/'.($year-1).' %'
				) {
					$tableType = DIRECTORATE_AGENCY_TABLE;
					//echo 'table: DIRECTORATE_AGENCY_TABLE'.'<br />'.PHP_EOL;
				}*/
				break;
			case AGENCY:
				if (
					$rowText == 'Kosten und,,,Voranschlag,,Voranschlag,Rechnung,,Rechnung,,,'
					&& $nextRow == 'Erlöse,,,'.$year.',,'.($year-1).','.($year-2).',,'.($year-3).',,,'
				) {
					$tableType = AGENCY_TABLE;
					#echo 'table: AGENCY_TABLE'.'<br />'.PHP_EOL;
				}
				break;
			case PRODUCT_GROUP:
				if (
					$rowText == 'Kosten und,,Voranschlag,Voranschlag,,Rechnung,Rechnung,,Finanzierung der Produktegruppe in %,,,'
					&& $nextRow == 'Erlöse,,'.$year.','.($year-1).',,'.($year-2).','.($year-3).',,,,,'
				) {
					$tableType = PRODUCT_GROUP_TABLE;
					#echo 'table: PRODUCT_GROUP_TABLE'.'<br />'.PHP_EOL;
				}
				else if(
					$rowText == 'Nummer,Produkt,Bruttokosten '.$year.',,,Erlös '.$year.',,,Nettokosten,,Nettokosten,Abweichung'
					&& $nextRow == ',,Fr.,%,,Fr.,%,,'.$year.' / Fr.,,'.($year-1).' / Fr.,'.$year.'/'.($year-1).' %'
				) {
					$tableType = PRODUCT_TABLE;
					#echo 'table: PRODUCT_TABLE'.'<br />'.PHP_EOL;
				}
				else if(
					$rowText == 'Nummer,Produkt,Bruttokosten '.$year.',,,Erlös '.$year.',,,Nettoerlös,,Nettoerlös,Abweichung'
					&& $nextRow == ',,Fr.,%,,Fr.,%,,'.$year.' / Fr.,,'.($year-1).' / Fr.,'.$year.'/'.($year-1).' %'
				) {
					$tableType = PRODUCT_REVENUE_TABLE;
					#echo 'table: PRODUCT_REVENUE_TABLE'.'<br />'.PHP_EOL;
				}
				break;
		}
		
		switch($tableType) {
			case PRODUCT_TABLE:
			case PRODUCT_REVENUE_TABLE:
				if(preg_match('/^P[0-9]{6}$/', $col0)) {
					$agencyNumber = substr($col0, 1, 2).'0';
					if($agencyNumber != $agency['number']) {
						echo 'WARNING: Agency "'.$agencyNumber.'" does not match current agency "'.$agency['number'].'".<br />'.PHP_EOL;
						continue;
					}
					if($col0 == 'P130210') {
						$productGroupNumber = 'PG130100';
					}
					else {
						//ToDo: verify that this is a correct assumtion
						$productGroupNumber = 'PG'.substr($col0, 1, 4).'00';
					}
					if($productGroupNumber != $productGroup['number']) {
						echo 'WARNING: Product group "'.$productGroupNumber.'" does not match current product group "'.$productGroup['number'].'".<br />'.PHP_EOL;
						continue;
					}
					
					if($tableType == PRODUCT_REVENUE_TABLE) {
						$productGroup['products'][$col0] = $productRevenueRowProcessor($row);
					}
					else {
						$productGroup['products'][$col0] = $productCostRowProcessor($row);
					}
				}
				break;
			case PRODUCT_GROUP_TABLE:
				if($row[1] == 'Bruttokosten') {
					$productGroup['gross_cost'] = array(
						'budgets' => array(
							$year => convert_to_float($row[2]),
							$year-1 => convert_to_float($row[3])
						),
						'accounts' => array(
							$year-2 => convert_to_float($row[5]),
							$year-3 => convert_to_float($row[6])
						)
					);
				}
				else if($row[1] == 'Erlöse') {
					$productGroup['revenue'] = array(
						'budgets' => array(
							$year => convert_to_float($row[2]),
							$year-1 => convert_to_float($row[3])
						),
						'accounts' => array(
							$year-2 => convert_to_float($row[5]),
							$year-3 => convert_to_float($row[6])
						)
					);
				}
				else if($row[1] == 'Nettokosten') {
					$productGroup['net_cost']['accounts'][$year-3] = convert_to_float($row[6]);
					
					$row[5] = convert_to_float($row[5]);
					
					if($productGroup['net_cost']['budgets'][$year] != convert_to_float($row[2])) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget '.$year.'.<br />'.PHP_EOL;
					}
					if($productGroup['net_cost']['budgets'][$year-1] != convert_to_float($row[3])) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget '.($year-1).'.<br />'.PHP_EOL;
					}
					if($productGroup['net_cost']['accounts'][$year-2] != $row[5]) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost net cost '.($year-2).': "'.$productGroup['net_cost']['accounts'][$year-2].'" vs "'.$row[5].'".<br />'.PHP_EOL;
					}
				}
				else if($row[1] == 'Nettoerlös') {
					$productGroup['net_cost']['accounts'][$year-3] = convert_to_float($row[6])*-1;
					$row[2] = convert_to_float($row[2])*-1;
					$row[3] = convert_to_float($row[3])*-1;
					$row[5] = convert_to_float($row[5])*-1;
					if($productGroup['net_cost']['budgets'][$year] != $row[2]) {
						//TODO: verify with City of Bern / notify of mistake
						if(!(
							in_array($productGroup['number'], array('PG230200','PG230300','PG240200','PG290100','PG300300','PG310300','PG510400','PG610200','PG610400','PG630200','PG630400','PG650100','PG650200','PG660100','PG660200','PG690100')) &&
							$productGroup['net_cost']['budgets'][$year] == $row[2]*-1
						)) {
							echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget '.$year.': "'.$productGroup['net_cost']['budgets'][$year].'" vs "'.$row[2].'".<br />'.PHP_EOL;
						}
					}
					if($productGroup['net_cost']['budgets'][$year-1] != $row[3]) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget '.($year-1).': "'.$productGroup['net_cost']['budgets'][$year-1].'" vs "'.$row[3].'".<br />'.PHP_EOL;
					}
					if($productGroup['net_cost']['accounts'][$year-2] != $row[5]) {
						echo ' WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost net cost '.($year-2).': "'.$productGroup['net_cost']['accounts'][$year-2].'" vs "'.$row[5].'".<br />'.PHP_EOL;
					}
				}
				break;
			case AGENCY_TABLE:
				if($row[1] == 'Bruttokosten') {
					$agency['gross_cost'] = array(
						'budgets' => array(
							$year => convert_to_float($row[3]),
							$year-1 => convert_to_float($row[5])
						),
						'accounts' => array(
							$year-2 => convert_to_float($row[6]),
							$year-3 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Erlöse') {
					$agency['revenue'] = array(
						'budgets' => array(
							$year => convert_to_float($row[3]),
							$year-1 => convert_to_float($row[5])
						),
						'accounts' => array(
							$year-2 => convert_to_float($row[6]),
							$year-3 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Nettokosten') {
					$agency['net_cost']['accounts'][$year-3] = convert_to_float($row[8]);
					
					$row[6] = convert_to_float($row[6]);
					
					if($agency['net_cost']['budgets'][$year] != convert_to_float($row[3])) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget '.$year.'.<br />'.PHP_EOL;
					}
					if($agency['net_cost']['budgets'][$year-1] != convert_to_float($row[5])) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget '.($year-1).'.<br />'.PHP_EOL;
					}
					if($agency['net_cost']['accounts'][$year-2] != $row[6]) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost net cost '.($year-2).': "'.$agency['net_cost']['accounts'][$year-2].'" vs "'.$row[6].'".<br />'.PHP_EOL;
					}
				}
				else if($row[1] == 'Nettoerlös') {
					$agency['net_cost']['accounts'][$year-3] = convert_to_float($row[8])*-1;
					
					$row[3] = convert_to_float($row[3])*-1;
					$row[5] = convert_to_float($row[5])*-1;
					$row[6] = convert_to_float($row[6])*-1;
					
					if($agency['net_cost']['budgets'][$year] != $row[3]) {
						//TODO: verify with City of Bern / notify of mistake
						if(
							!($directorate['number'] == '1200' && $agency['number'] == '290' && $agency['net_cost']['budgets'][$year] == $row[3]*-1)
							&&
							!($directorate['number'] == '1200' && $agency['number'] == '240' && $agency['net_cost']['budgets'][$year] == $row[3]*-1)
							&&
							!($directorate['number'] == '1300' && $agency['number'] == '300' && $agency['net_cost']['budgets'][$year] == $row[3]*-1)
							&&
							!($directorate['number'] == '1600' && in_array($agency['number'], array('610', '630', '690')) && $agency['net_cost']['budgets'][$year] == $row[3]*-1)
						)
						{
							echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget '.$year.': "'.$agency['net_cost']['budgets'][$year].'" vs "'.$row[3].'".<br />'.PHP_EOL;
						}
					}
					if($agency['net_cost']['budgets'][$year-1] != $row[5]) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget '.($year-1).': "'.$agency['net_cost']['budgets'][$year-1].'" vs "'.$row[5].'".<br />'.PHP_EOL;
					}
					if($agency['net_cost']['accounts'][$year-2] != $row[6]) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost net cost '.($year-2).': "'.$agency['net_cost']['accounts'][$year-2].'" vs "'.$row[6].'".<br />'.PHP_EOL;
					}
				}
				break;
			case DIRECTORATE_TABLE:
				if($row[1] == 'Bruttokosten') {
					$directorate['gross_cost'] = array(
						'budgets' => array(
							$year => convert_to_float($row[3]),
							$year-1 => convert_to_float($row[5])
						),
						'accounts' => array(
							$year-2 => convert_to_float($row[6]),
							$year-3 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Erlöse') {
					$directorate['revenue'] = array(
						'budgets' => array(
							$year => convert_to_float($row[3]),
							$year-1 => convert_to_float($row[5])
						),
						'accounts' => array(
							$year-2 => convert_to_float($row[6]),
							$year-3 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Nettokosten') {
					$directorate['net_cost']['accounts'][$year-3] = convert_to_float($row[8]);
					
					if($directorate['net_cost']['budgets'][$year] != convert_to_float($row[3])) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget '.$year.'.<br />'.PHP_EOL;
					}
					if($directorate['net_cost']['budgets'][$year-1] != convert_to_float($row[5])) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget '.($year-1).'.<br />'.PHP_EOL;
					}
					if($directorate['net_cost']['accounts'][$year-2] != convert_to_float($row[6])) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost net cost '.($year-2).'.<br />'.PHP_EOL;
					}
				}
				else if($row[1] == 'Nettoerlös') {
					$directorate['net_cost']['accounts'][$year-3] = convert_to_float($row[8])*-1;
					
					$row[3] = convert_to_float($row[3])*-1;
					$row[5] = convert_to_float($row[5])*-1;
					$row[6] = convert_to_float($row[6])*-1;
					
					if($directorate['net_cost']['budgets'][$year] != $row[3]) {
						//TODO: verify with City of Bern / notify of mistake
						if(!($directorate['number'] == '1600' && $directorate['net_cost']['budgets'][$year] == $row[3]*-1)) {
							echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget '.$year.': "'.$directorate['net_cost']['budgets'][$year].'" vs "'.$row[3].'"<br />'.PHP_EOL;
						}
					}
					if($directorate['net_cost']['budgets'][$year-1] != $row[5]) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget '.($year-1).'.<br />'.PHP_EOL;
					}
					if($directorate['net_cost']['accounts'][$year-2] != $row[6]) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost net cost '.($year-2).'.<br />'.PHP_EOL;
					}
				}
				break;
		}
	}
	
	if(!isset($directorate['gross_cost'])) {
		echo 'MISSING: '.$directorate['number'].' gross cost.<br />'.PHP_EOL;
	}
	if(!isset($directorate['revenue'])) {
		echo 'MISSING: '.$directorate['number'].' revenue.<br />'.PHP_EOL;
	}
	if(!isset($directorate['net_cost']['accounts'][$year-3])) {
		echo 'MISSING: '.$directorate['number'].' net cost account '.($year-3).'.<br />'.PHP_EOL;
	}
	foreach($directorate['agencies'] as &$agency) {
		if(!isset($agency['gross_cost'])) {
			echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' gross cost.<br />'.PHP_EOL;
		}
		if(!isset($agency['revenue'])) {
			echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' revenue.<br />'.PHP_EOL;
		}
		if(!isset($agency['net_cost']['accounts'][$year-3])) {
			echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' net cost account '.($year-3).'.<br />'.PHP_EOL;
		}
		foreach($agency['product_groups'] as &$product_group) {
			if(!isset($product_group['gross_cost'])) {
				echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' '.$product_group['number'].' gross cost.<br />'.PHP_EOL;
			}
			if(!isset($product_group['revenue'])) {
				echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' '.$product_group['number'].' revenue.<br />'.PHP_EOL;
			}
			if(!isset($product_group['net_cost']['accounts'][$year-3])) {
				echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' '.$product_group['number'].' net cost account '.($year-3).'.<br />'.PHP_EOL;
			}
		}
	}
}

$directorates2012 = json_decode(file_get_contents('data/bern/directorates.json'), true);
//var_dump($directorates2012);

foreach($directorates as $dKey => &$directorate) {
	foreach($directorate['agencies'] as $aKey => &$agency) {
		foreach($agency['product_groups'] as $pgKey => &$product_group) {
			foreach($product_group['products'] as $pKey => &$product) {
				if(isset(
					$directorates2012[$dKey]['agencies'][$aKey]['product_groups'][$pgKey]['products'][$pKey]
				)) {
					$product2012 = $directorates2012[$dKey]['agencies'][$aKey]['product_groups'][$pgKey]['products'][$pKey];
					$gross_cost2012 = $product2012['gross_cost']['budgets']['2012'];
					$revenue2012 = $product2012['revenue']['budgets']['2012'];
				}
				else {
					$gross_cost2012 = 0;
					$revenue2012 = 0;
				}
				$product['gross_cost']['budgets']['2012'] = $gross_cost2012;
				$product['revenue']['budgets']['2012'] = $revenue2012;
			}
		}
	}
}

// echo '<pre>';
// var_dump($directorates);
// echo '</pre>';

file_put_contents('data/bern/directorates'.$year.'.json', json_encode($directorates));

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
			$productGroupChilds = array();
			$productGroupSpending = 0.0;
			foreach($product_group['products'] as &$product) {
				if($product['net_cost']['budgets'][$year] > 0) {
					$productGroupChilds[] = array(
						'name' => $product['name'],
						'type' => 'product',
						'size' => ceil($product['net_cost']['budgets'][$year])
					);
					$productGroupSpending += $product['net_cost']['budgets'][$year];
				}
			}
			if(!empty($productGroupChilds)) {
				$agencyChilds[] = array(
					'name' => $product_group['name'],
					'type' => 'product_group',
					'size' => ceil($productGroupSpending),
					'children' => $productGroupChilds
				);
			}
			$agencySpending += $productGroupSpending;
		}
		if(!empty($agencyChilds)) {
			$directorateChild = array(
				'name' => $agency['name'],
				'type' => 'agency',
				'size' => ceil($agencySpending),
				'children' => $agencyChilds
			);
			$directorateChilds[] = $directorateChild;
		}
		$directorateSpending += $agencySpending;
	}
	if(!empty($directorateChilds)) {
		$rootChild = array(
			'name' => $directorateDisplayName[$directorate['number']],
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

/*echo '<pre>';
echo json_encode($flare);
echo '</pre>';*/

// file_put_contents('data/bern/flareWithProducts'.$year.'.json', json_encode($flare));

$newFlare = array(
	'meta' => array(
		'name' => 'Stadt Bern Budget '.$year,
		'hierarchy' => array('Direktion', 'Dienststelle', 'Produktegruppe', 'Produkt')
	)//,
	// 'type' => 'city'
);
$rootChilds = array();
foreach($directorates as &$directorate) {
	$directorateChilds = array();
	foreach($directorate['agencies'] as &$agency) {
		$agencyChilds = array();
		foreach($agency['product_groups'] as &$product_group) {
			$productGroupChilds = array();
			foreach($product_group['products'] as $product_id => &$product) {

				// if($product['net_cost']['budgets'][$year] > 0) {
					$productGroupChilds[] = array(
						'id' => $product_id,
						'name' => $product['name'],
						// 'type' => 'product',
						'gross_cost' => $product['gross_cost'],
						'revenue' => $product['revenue']
					);
				// }
			}
			if(!empty($productGroupChilds)) {
				$agencyChilds[] = array(
					'id' => $product_group['number'],
					'name' => $product_group['name'],
					// 'type' => 'product_group',
					'gross_cost' => $product_group['gross_cost'],
					'revenue' => $product_group['revenue'],
					'children' => $productGroupChilds
				);
			}
		}
		if(!empty($agencyChilds)) {
			$directorateChild = array(
				'id' => $agency['number'],
				'name' => $agency['name'],
				// 'type' => 'agency',
				'gross_cost' => $agency['gross_cost'],
				'revenue' => $agency['revenue'],
				'children' => $agencyChilds
			);
			$directorateChilds[] = $directorateChild;
		}
	}
	if(!empty($directorateChilds)) {
		$rootChild = array(
			'id' => $directorate['number'],
			'name' => $directorate['name']
		);
		if(isset($directorate['acronym'])) {
			$rootChild['acronym'] = $directorate['acronym'];
		}
		$rootChild = array_merge($rootChild, array(
			// 'type' => 'directorate',
			'gross_cost' => $directorate['gross_cost'],
			'revenue' => $directorate['revenue'],
			'children' => $directorateChilds
		));
		$rootChilds[] = $rootChild;
	}
}
$newFlare['nodes'] = $rootChilds;


// echo '<pre>';
// echo json_encode($newFlare);
// echo '</pre>';

file_put_contents('data/bern/bernbudget'.$year.'.ogd.json', json_encode($newFlare));

//CSV for openspending (not working yet)
/* CSV export not yet working with extended data */
// $csv = 'nummer,direktion,dienststelle,produktgruppe,produkt,date,budget'."\n";
// foreach($directorates as &$directorate) {
// 	foreach($directorate['agencies'] as &$agency) {
// 		foreach($agency['product_groups'] as &$product_group) {
// 			foreach($product_group['products'] as &$product) {
// 			var_dump($product);
// 			if($product['net_cost']['budgets'][$year] > 0) {
// 				$csv .= $product_group['number'].',"'.$directorate['name'].'","'.$agency['name'].'","'.$product_group['name'].'","'.$product['name'].'",'.$year.','.ceil($product['net_cost']['budgets'][$year])."\n";
// 			}
// 			}
// 		}
// 	}
// }
#file_put_contents('data/bern/stadtbernbudget'.$year.'.csv', $csv);

function twoDecimals($number) {
	return number_format($number, 2, '.', '');
}

function generateCSVRow($directorate, $agency = NULL, $product_group = NULL, $product = NULL) {
	$numberRow = current(array_filter(array($product, $product_group, $agency, $directorate)));
	$csvRow = '0000,'.$directorate['number'].','.($agency ? $agency['number'] : '').','.($product_group ? $product_group['number'] : '').','.($product ? $product['number'] : '');
	// 2013
	$csvRow .= ','.twoDecimals($numberRow['gross_cost']['budgets'][2013]);
	$csvRow .= ','.twoDecimals($numberRow['revenue']['budgets'][2013]);
	// 2012
	$csvRow .= ','.twoDecimals($numberRow['gross_cost']['budgets'][2012]);
	$csvRow .= ','.twoDecimals($numberRow['revenue']['budgets'][2012]);
	// 2011
	$csvRow .= ','.twoDecimals($numberRow['gross_cost']['accounts'][2011]);
	$csvRow .= ','.twoDecimals($numberRow['revenue']['accounts'][2011]);

	$csvRow .= "\n";
	return $csvRow;
}

// CSV according to Rolf Studer proposal
// $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
// $csv .= 'PG-Budget,,,,,Plan 2013, Plan 2013,Plan 2012,Plan 2012,Ist 2011,Ist 2011'."\n";
// $csv .= 'Stadt,Direktion,Dienststelle,Produktegruppe,Produkt,Bruttokosten,"Erlöse",Bruttokosten,"Erlöse",Bruttokosten,"Erlöse"'."\n";
// $totals = array(
// 	'number' => '',
// 	'gross_cost' => array(
// 		'budgets' => array(
// 			2013 => 0,
// 			2012 => 0
// 		),
// 		'accounts' => array(
// 			2011 => 0
// 		)
// 	),
// 	'revenue' => array(
// 		'budgets' => array(
// 			2013 => 0,
// 			2012 => 0
// 		),
// 		'accounts' => array(
// 			2011 => 0
// 		)
// 	)
// );
// foreach($directorates as &$directorate) {
// 	$totals['gross_cost']['budgets'][2013] += $directorate['gross_cost']['budgets'][2013];
// 	$totals['gross_cost']['budgets'][2012] += $directorate['gross_cost']['budgets'][2012];
// 	$totals['revenue']['budgets'][2013] += $directorate['revenue']['budgets'][2013];
// 	$totals['revenue']['budgets'][2012] += $directorate['revenue']['budgets'][2012];
// 	$totals['gross_cost']['accounts'][2011] += $directorate['gross_cost']['accounts'][2011];
// 	$totals['revenue']['accounts'][2011] += $directorate['revenue']['accounts'][2011];
// }
// $csv .= generateCSVRow($totals);
// foreach($directorates as &$directorate) {
// 	$csv .= generateCSVRow($directorate);
// 	foreach($directorate['agencies'] as &$agency) {
// 		$csv .= generateCSVRow($directorate, $agency);
// 		foreach($agency['product_groups'] as &$product_group) {
// 			$csv .= generateCSVRow($directorate, $agency, $product_group);
// 			foreach($product_group['products'] as &$product) {
// 				$csv .= generateCSVRow($directorate, $agency, $product_group, $product);
// 			}
// 		}
// 	}
// }

// file_put_contents('data/bern/stadt-bern-pg-budget-2013.csv', $csv);

?>
	</body>
</html>