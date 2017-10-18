<?php
function run()
{
	switch(@$_REQUEST['act'])
	{
		case 'geocode':
			if($_REQUEST['format'] == 'gisgraphy_json')
			{
				$address = urlencode($_REQUEST['address']);
				$out = core_curl_post(
					"http://services.gisgraphy.com/geocoding/geocode?format=json&callback=?&address=".$address,
					NULL,
					NULL,
					NULL,
					"GET"
				);
			}
			echo $out;
			core_terminate();
			break;

		default:
			break;
	}
}
?>