<?php

namespace PozarniPoplach;

use Janmensik\Jmlib\Modul;
use Janmensik\Jmlib\Database;

class Ad extends Modul {
	var $sql_base = 'SELECT SQL_CALC_FOUND_ROWS ad.id, ad.status, ad.banner_image_url, ad.target_link, ad.ad_text, ad.promo_code, adc.name, IFNULL(SUM(adh.display_count), 0) AS display_count_total FROM advert ad JOIN advertiser adc ON ad.advertiser_id=adc.id LEFT JOIN advert_hit adh ON ad.id=adh.advert_id GROUP BY ad.id'; # zaklad SQL dotazu
	var $sql_update = 'UPDATE advert ad'; # zaklad SQL dotazu - UPDATE
	var $sql_insert = 'INSERT INTO advert'; # zaklad SQL dotazu - INSERT
	var $sql_table = 'ad';
	var $order = 8;

	//var $fulltext_columns = array('hub.id', 'hub.title', 'hub.pincode');
	var $limit = -1;


	public $cache;

	# ...................................................................
	public function __construct(Database &$database) {
		parent::__construct($database);
	}

	# ...................................................................
	public function getAd(int $unit_id): array|null {
		# Conditions: Only Active ads
		$where = array('ad.status="active"');

		$ad = $this->getRandom($where, 8, 10, null, 1);

		if (!empty($ad)) {
			if ($ad[0]['target_link']) {
				// Generate QR code using chillerlan/php-qrcode
				// We return it as a Data URI so it can be directly used in an <img> tag
				$options = new \chillerlan\QRCode\QROptions([
					'version'      => \chillerlan\QRCode\QRCode::VERSION_AUTO,
					'outputType'   => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
					'eccLevel'     => \chillerlan\QRCode\QRCode::ECC_L,
					'addQuietzone' => true,
					'svgViewBox'   => true, // Important for responsive scaling
				]);

				$ad[0]['qr_code_data'] = (new \chillerlan\QRCode\QRCode($options))->render($ad[0]['target_link']);
			}

			# add advert view +1 hit
			$this->DB->query('INSERT INTO advert_hit (advert_id, unit_id, display_count) VALUES ("'.$ad[0]['id'].'", "'.$unit_id.'", 1) ON DUPLICATE KEY UPDATE display_count = display_count + 1, last_displayed_at = CURRENT_TIMESTAMP;');

			return $ad[0];
		}

		return null;
	}
}
