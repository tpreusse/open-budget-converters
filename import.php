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
	$number = str_replace(',', '', clean_text($number));
	return round((float)$number, 2);
}

foreach($overviewRows[0] as &$row) {
	$row = explode(',', $row);
	if(preg_match('/^[0-9]{4}$/', $row[1])) {
		$directorates[$row[1]] = array(
			'number' => $row[1],
			'name' => clean_text($row[2]),
			'net_cost' => array(
				'budgets' => array(
					2012 => convert_to_float($row[5]),
					2011 => convert_to_float($row[6])
				),
				'accounts' => array(
					2010 => convert_to_float($row[7])
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
					2012 => convert_to_float($row[5]),
					2011 => convert_to_float($row[6])
				),
				'accounts' => array(
					2010 => convert_to_float($row[7])
				)
			),
			'product_groups' => array()
		);
		$curAgency = $row[2];
	}
	else if(!is_null($curAgency) && preg_match('/^PG[0-9]{6}$/', $row[3])) {
		//TODO: verify with city of bern that this is a valid correction
		if($row[3] == 'PG130000') {
			$row[3] = 'PG130100';
		}
		$directorates[$curDirectorate]['agencies'][$curAgency]['product_groups'][$row[3]] = array(
			'number' => $row[3],
			'name' => clean_text($row[4]),
			'net_cost' => array(
				'budgets' => array(
					2012 => convert_to_float($row[5]),
					2011 => convert_to_float($row[6])
				),
				'accounts' => array(
					2010 => convert_to_float($row[7])
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
				2012 => convert_to_float($row[8]),
				2011 => convert_to_float($row[10])
			)
		),
		'gross_cost' => array(
			'budgets' => array(
				2012 => convert_to_float($row[2])
			)
		),
		'revenue' => array(
			'budgets' => array(
				2012 => convert_to_float($row[5])
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

define('DIRECTORATE', 1);
define('AGENCY', 2);
define('PRODUCT_GROUP', 3);

define('DIRECTORATE_TABLE', 1);
//define('DIRECTORATE_AGENCY_TABLE', 2);
define('AGENCY_TABLE', 2);
define('PRODUCT_GROUP_TABLE', 3);
define('PRODUCT_TABLE', 4);
define('PRODUCT_REVENUE_TABLE', 5);

foreach($directorates as &$directorate) {
	$directorateFileName = 'data/source/'.$directorate['number'].'.csv';
	if(!file_exists($directorateFileName)/* || $directorate['number'] != '1200'*/) {
		continue;
	}
	$directorateFile = file_get_contents($directorateFileName);
	preg_match_all('/\n([^,]*,){11}.*/', $directorateFile, $directorateRows);
	
	$sectionType = NULL;
	$tableType = NULL;
	$agency = NULL;
	
	$directorateRowCount = count($directorateRows[0])-1;
	//echo 'count '.$directorateRowCount.'<br />';
	
	foreach($directorateRows[0] as $rowNumber => &$rowText) {
		//echo $rowNumber.' ';
		$row = explode(',', $rowText);
		$col0 = trim($row[0]);
		$rowType = NULL;
		
		$rowText = trim($rowText);
		if($rowText == ',,,,,,,,,,,') {
			continue;
		}
		
		
		if(preg_match('/^[0-9]{4}$/', $col0) && clean_text(trim($row[1])) == ($directorate['name'].(isset($directorate['acronym']) ? ' ('.$directorate['acronym'].')' : ''))) {
			$sectionType = DIRECTORATE;
			//echo 'section: DIRECTORATE '.$rowText.'<br />'.PHP_EOL;
			continue;
		}
		else if(preg_match('/^([0-9]{3})(-[0-9]{3})?,[^,]*,,,,,,,,,,$/', $rowText, $agencyMatches)) {
			$sectionType = AGENCY;
			$tableType = NULL;
			if(!isset($directorate['agencies'][$agencyMatches[1]])) {
				echo 'WARNING: Agency "'.$agencyMatches[1].'" not found.<br />'.PHP_EOL;
			}
			else {
				$agency = &$directorate['agencies'][$agencyMatches[1]];
			}
			//echo 'section: AGENCY '.$rowText.'<br />'.PHP_EOL;
			continue;
		}
		else if(preg_match('/^,Produktegruppe (PG[0-9]{6}) [^,]+,,,,,,,,,,$/', $rowText, $productGroupMatches)) {
			$sectionType = PRODUCT_GROUP;
			$tableType = NULL;
			if(!isset($agency['product_groups'][$productGroupMatches[1]])) {
				echo 'WARNING: Product group "'.$productGroupMatches[1].'" not found.<br />'.PHP_EOL;
			}
			else {
				$productGroup = &$agency['product_groups'][$productGroupMatches[1]];
			}
			//echo 'section: AGENCY '.$rowText.'<br />'.PHP_EOL;
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
					&& $nextRow == 'Erlöse,,,2012,,2011,2010,,2009,,,'
				) {
					$tableType = DIRECTORATE_TABLE;
					//echo 'table: DIRECTORATE_TABLE'.'<br />'.PHP_EOL;
				}
				/*else if (
					$rowText == 'Nummer,Dienststelle,Bruttokosten 2012,,,Erlös 2012,,,Nettokosten,,Nettokosten,Abweichung'
					&& $nextRow == ',,Fr.,%,,Fr.,%,,2012 / Fr.,,2011 / Fr.,2012/2011 %'
				) {
					$tableType = DIRECTORATE_AGENCY_TABLE;
					//echo 'table: DIRECTORATE_AGENCY_TABLE'.'<br />'.PHP_EOL;
				}*/
				break;
			case AGENCY:
				if (
					$rowText == 'Kosten und,,,Voranschlag,,Voranschlag,Rechnung,,Rechnung,,,'
					&& $nextRow == 'Erlöse,,,2012,,2011,2010,,2009,,,'
				) {
					$tableType = AGENCY_TABLE;
					//echo 'table: AGENCY_TABLE'.'<br />'.PHP_EOL;
				}
				break;
			case PRODUCT_GROUP:
				if (
					$rowText == 'Kosten und,,Voranschlag,Voranschlag,,Rechnung,Rechnung,,Finanzierung der Produktegruppe in %,,,'
					&& $nextRow == 'Erlöse,,2012,2011,,2010,2009,,,,,'
				) {
					$tableType = PRODUCT_GROUP_TABLE;
					//echo 'table: PRODUCT_GROUP_TABLE'.'<br />'.PHP_EOL;
				}
				else if(
					$rowText == 'Nummer,Produkt,Bruttokosten 2012,,,Erlös 2012,,,Nettokosten,,Nettokosten,Abweichung'
					&& $nextRow == ',,Fr.,%,,Fr.,%,,2012 / Fr.,,2011 / Fr.,2012/2011 %'
				) {
					$tableType = PRODUCT_TABLE;
					//echo 'table: PRODUCT_TABLE'.'<br />'.PHP_EOL;
				}
				else if(
					$rowText == 'Nummer,Produkt,Bruttokosten 2012,,,Erlös 2012,,,Nettoerlös,,Nettoerlös,Abweichung'
					&& $nextRow == ',,Fr.,%,,Fr.,%,,2012 / Fr.,,2011 / Fr.,2012/2011 %'
				) {
					$tableType = PRODUCT_REVENUE_TABLE;
					//echo 'table: PRODUCT_REVENUE_TABLE'.'<br />'.PHP_EOL;
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
							2012 => convert_to_float($row[2]),
							2011 => convert_to_float($row[3])
						),
						'accounts' => array(
							2010 => convert_to_float($row[5]),
							2009 => convert_to_float($row[6])
						)
					);
				}
				else if($row[1] == 'Erlöse') {
					$productGroup['revenue'] = array(
						'budgets' => array(
							2012 => convert_to_float($row[2]),
							2011 => convert_to_float($row[3])
						),
						'accounts' => array(
							2010 => convert_to_float($row[5]),
							2009 => convert_to_float($row[6])
						)
					);
				}
				else if($row[1] == 'Nettokosten') {
					$productGroup['net_cost']['accounts'][2009] = convert_to_float($row[6]);
					
					$row[5] = convert_to_float($row[5]);
					
					if($productGroup['net_cost']['budgets'][2012] != convert_to_float($row[2])) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget 2012.<br />'.PHP_EOL;
					}
					if($productGroup['net_cost']['budgets'][2011] != convert_to_float($row[3])) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget 2011.<br />'.PHP_EOL;
					}
					if($productGroup['net_cost']['accounts'][2010] != $row[5]) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost net cost 2010: "'.$productGroup['net_cost']['accounts'][2010].'" vs "'.$row[5].'".<br />'.PHP_EOL;
					}
				}
				else if($row[1] == 'Nettoerlös') {
					$productGroup['net_cost']['accounts'][2009] = convert_to_float($row[6])*-1;
					$row[2] = convert_to_float($row[2])*-1;
					$row[3] = convert_to_float($row[3])*-1;
					$row[5] = convert_to_float($row[5])*-1;
					if($productGroup['net_cost']['budgets'][2012] != $row[2]) {
						//TODO: verify with City of Bern / notify of mistake
						if(!(
							in_array($productGroup['number'], array('PG230200','PG230300','PG240200','PG290100','PG300300','PG310300','PG510400','PG610200','PG610400','PG630200','PG630400','PG650100','PG650200','PG660100','PG660200','PG690100')) &&
							$productGroup['net_cost']['budgets'][2012] == $row[2]*-1
						)) {
							echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget 2012: "'.$productGroup['net_cost']['budgets'][2012].'" vs "'.$row[2].'".<br />'.PHP_EOL;
						}
					}
					if($productGroup['net_cost']['budgets'][2011] != $row[3]) {
						echo 'WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost budget 2011: "'.$productGroup['net_cost']['budgets'][2011].'" vs "'.$row[3].'".<br />'.PHP_EOL;
					}
					if($productGroup['net_cost']['accounts'][2010] != $row[5]) {
						echo ' WARNING: '.$directorate['number'].' '.$productGroup['number'].' conflicting net_cost net cost 2010: "'.$productGroup['net_cost']['accounts'][2010].'" vs "'.$row[5].'".<br />'.PHP_EOL;
					}
				}
				break;
			case AGENCY_TABLE:
				if($row[1] == 'Bruttokosten') {
					$agency['gross_cost'] = array(
						'budgets' => array(
							2012 => convert_to_float($row[3]),
							2011 => convert_to_float($row[5])
						),
						'accounts' => array(
							2010 => convert_to_float($row[6]),
							2009 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Erlöse') {
					$agency['revenue'] = array(
						'budgets' => array(
							2012 => convert_to_float($row[3]),
							2011 => convert_to_float($row[5])
						),
						'accounts' => array(
							2010 => convert_to_float($row[6]),
							2009 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Nettokosten') {
					$agency['net_cost']['accounts'][2009] = convert_to_float($row[8]);
					
					$row[6] = convert_to_float($row[6]);
					
					if($agency['net_cost']['budgets'][2012] != convert_to_float($row[3])) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget 2012.<br />'.PHP_EOL;
					}
					if($agency['net_cost']['budgets'][2011] != convert_to_float($row[5])) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget 2011.<br />'.PHP_EOL;
					}
					if($agency['net_cost']['accounts'][2010] != $row[6]) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost net cost 2010: "'.$agency['net_cost']['accounts'][2010].'" vs "'.$row[6].'".<br />'.PHP_EOL;
					}
				}
				else if($row[1] == 'Nettoerlös') {
					$agency['net_cost']['accounts'][2009] = convert_to_float($row[8])*-1;
					
					$row[3] = convert_to_float($row[3])*-1;
					$row[5] = convert_to_float($row[5])*-1;
					$row[6] = convert_to_float($row[6])*-1;
					
					if($agency['net_cost']['budgets'][2012] != $row[3]) {
						//TODO: verify with City of Bern / notify of mistake
						if(
							!($directorate['number'] == '1200' && $agency['number'] == '290' && $agency['net_cost']['budgets'][2012] == $row[3]*-1)
							&&
							!($directorate['number'] == '1200' && $agency['number'] == '240' && $agency['net_cost']['budgets'][2012] == $row[3]*-1)
							&&
							!($directorate['number'] == '1300' && $agency['number'] == '300' && $agency['net_cost']['budgets'][2012] == $row[3]*-1)
							&&
							!($directorate['number'] == '1600' && in_array($agency['number'], array('610', '630', '690')) && $agency['net_cost']['budgets'][2012] == $row[3]*-1)
						)
						{
							echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget 2012: "'.$agency['net_cost']['budgets'][2012].'" vs "'.$row[3].'".<br />'.PHP_EOL;
						}
					}
					if($agency['net_cost']['budgets'][2011] != $row[5]) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost budget 2011: "'.$agency['net_cost']['budgets'][2011].'" vs "'.$row[5].'".<br />'.PHP_EOL;
					}
					if($agency['net_cost']['accounts'][2010] != $row[6]) {
						echo 'WARNING: '.$directorate['number'].' '.$agency['number'].' conflicting net_cost net cost 2010: "'.$agency['net_cost']['accounts'][2010].'" vs "'.$row[6].'".<br />'.PHP_EOL;
					}
				}
				break;
			case DIRECTORATE_TABLE:
				if($row[1] == 'Bruttokosten') {
					$directorate['gross_cost'] = array(
						'budgets' => array(
							2012 => convert_to_float($row[3]),
							2011 => convert_to_float($row[5])
						),
						'accounts' => array(
							2010 => convert_to_float($row[6]),
							2009 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Erlöse') {
					$directorate['revenue'] = array(
						'budgets' => array(
							2012 => convert_to_float($row[3]),
							2011 => convert_to_float($row[5])
						),
						'accounts' => array(
							2010 => convert_to_float($row[6]),
							2009 => convert_to_float($row[8])
						)
					);
				}
				else if($row[1] == 'Nettokosten') {
					$directorate['net_cost']['accounts'][2009] = convert_to_float($row[8]);
					
					if($directorate['net_cost']['budgets'][2012] != convert_to_float($row[3])) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget 2012.<br />'.PHP_EOL;
					}
					if($directorate['net_cost']['budgets'][2011] != convert_to_float($row[5])) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget 2011.<br />'.PHP_EOL;
					}
					if($directorate['net_cost']['accounts'][2010] != convert_to_float($row[6])) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost net cost 2010.<br />'.PHP_EOL;
					}
				}
				else if($row[1] == 'Nettoerlös') {
					$directorate['net_cost']['accounts'][2009] = convert_to_float($row[8])*-1;
					
					$row[3] = convert_to_float($row[3])*-1;
					$row[5] = convert_to_float($row[5])*-1;
					$row[6] = convert_to_float($row[6])*-1;
					
					if($directorate['net_cost']['budgets'][2012] != $row[3]) {
						//TODO: verify with City of Bern / notify of mistake
						if(!($directorate['number'] == '1600' && $directorate['net_cost']['budgets'][2012] == $row[3]*-1)) {
							echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget 2012: "'.$directorate['net_cost']['budgets'][2012].'" vs "'.$row[3].'"<br />'.PHP_EOL;
						}
					}
					if($directorate['net_cost']['budgets'][2011] != $row[5]) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost budget 2011.<br />'.PHP_EOL;
					}
					if($directorate['net_cost']['accounts'][2010] != $row[6]) {
						echo 'WARNING: '.$directorate['number'].' conflicting net_cost net cost 2010.<br />'.PHP_EOL;
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
	if(!isset($directorate['net_cost']['accounts'][2009])) {
		echo 'MISSING: '.$directorate['number'].' net cost account 2009.<br />'.PHP_EOL;
	}
	foreach($directorate['agencies'] as &$agency) {
		if(!isset($agency['gross_cost'])) {
			echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' gross cost.<br />'.PHP_EOL;
		}
		if(!isset($agency['revenue'])) {
			echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' revenue.<br />'.PHP_EOL;
		}
		if(!isset($agency['net_cost']['accounts'][2009])) {
			echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' net cost account 2009.<br />'.PHP_EOL;
		}
		foreach($agency['product_groups'] as &$product_group) {
			if(!isset($product_group['gross_cost'])) {
				echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' '.$product_group['number'].' gross cost.<br />'.PHP_EOL;
			}
			if(!isset($product_group['revenue'])) {
				echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' '.$product_group['number'].' revenue.<br />'.PHP_EOL;
			}
			if(!isset($product_group['net_cost']['accounts'][2009])) {
				echo 'MISSING: '.$directorate['number'].' '.$agency['number'].' '.$product_group['number'].' net cost account 2009.<br />'.PHP_EOL;
			}
		}
	}
}

echo '<pre>';
var_dump($directorates);
echo '</pre>';

//file_put_contents('data/directorates.json', json_encode($directorates));
/* flare export not yet working with extended data
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
echo '</pre>';*/

//file_put_contents('data/flare.json', json_encode($flare));

//CSV for openspending (not working yet)
/* CSV export not yet working with extended data
$csv = 'nummer;direktion;dienststelle;produktgruppe;date;budget'."\n";
foreach($directorates as &$directorate) {
	foreach($directorate['agencies'] as &$agency) {
		foreach($agency['product_groups'] as &$product_group) {
			if($product_group['budgets'][2012] > 0) {
				$csv .= $product_group['number'].';'.$directorate['name'].';'.$agency['name'].';'.$product_group['name'].';2012;'.ceil($product_group['budgets'][2012])."\n";
			}
		}
	}
}*/
//file_put_contents('data/stadtbernbudget2012.csv', $csv);

?>
	</body>
</html>