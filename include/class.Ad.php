<?php

namespace PozarniPoplach;

use Janmensik\Jmlib\Modul;
use Janmensik\Jmlib\Database;

class Ad extends Modul {
    protected ?string $sql_base = 'SELECT SQL_CALC_FOUND_ROWS ad.id, ad.title, ad.status, ad.banner_image_url, ad.target_link, ad.ad_text, ad.promo_code, ad.qr_code_svg, adc.name AS advertiser_name, IFNULL(SUM(adh.display_count), 0) AS display_count_total, IFNULL(SUM(adh.link_count), 0) AS link_count_total, adc.id AS advertiser_id FROM advert ad JOIN advertiser adc ON ad.advertiser_id=adc.id LEFT JOIN advert_hit adh ON ad.id=adh.advert_id GROUP BY ad.id'; # zaklad SQL dotazu
    protected ?string $sql_update = 'UPDATE advert ad'; # zaklad SQL dotazu - UPDATE
    protected ?string $sql_insert = 'INSERT INTO advert'; # zaklad SQL dotazu - INSERT
    protected ?string $sql_table = 'ad';
    protected int|string $order = 8;

    //protected $fulltext_columns = array('hub.id', 'hub.title', 'hub.pincode');
    protected int $limit = -1;

    public array $text = array(
        'cs' => array(
            'status' =>
            array('active' => 'Aktivní', 'disabled' => 'Pozastavený', 'deleted' => 'Smazaný')
        )
    );

    protected array $elements = [
        'title',
        'status',
        'banner_image_url',
        'target_link',
        'ad_text',
        'promo_code',
        'advertiser_id'
    ];

    # ...................................................................
    public function __construct(Database &$database) {
        parent::__construct($database);
    }

    # ...................................................................
    /**
     * Gets an advertisement for a specific device, implementing "Sticky Ad" logic.
     * Decisions (which ad or no ad) are persisted for a set duration.
     *
     * @param string $deviceUuid Hardware UUID of the device
     * @param int $unitId Unit ID the device belongs to
     * @return array|null
     */
    public function getAdForDevice(string $deviceUuid, int $unitId): array|null {
        // 1. Fetch current state and configuration for this device
        $device = $this->DB->getRow($this->DB->query(
            'SELECT ad_probability, ad_sticky_duration, current_ad_id, ad_expires_at
             FROM alarm_device_authorized
             WHERE device_uuid = "' . mysqli_real_escape_string($this->DB->db, $deviceUuid) . '"
             LIMIT 1'
        ));

        if (!$device) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $currentAdId = $device['current_ad_id'];

        // 2. Check if we are within a sticky window
        if (!empty($device['ad_expires_at']) && $device['ad_expires_at'] > $now) {
            // We have a valid window. If we have an ad ID, return its data.
            if ($currentAdId) {
                return $this->getAdData($currentAdId, $unitId, false); // Don't log hit every time
            }
            return null; // Sticky silence
        }

        // 3. Window expired or first run: Roll the dice
        $roll = random_int(1, 100);
        $newAdId = null;
        $expiresAt = date('Y-m-d H:i:s', time() + ($device['ad_sticky_duration'] * 60));

        if ($roll <= $device['ad_probability']) {
            // Roll successful: Pick a random active ad
            // Optimization: Used getNoCalcRows to avoid expensive SQL_CALC_FOUND_ROWS overhead
            $ads = $this->getNoCalcRows(['ad.status="active"'], null, 20); // Get up to 20 active ads
            if (!empty($ads)) {
                $randomAd = $ads[array_rand($ads)];
                $newAdId = $randomAd['id'];
            }
        }

        // 4. Persist the new state
        $this->DB->query(
            'UPDATE alarm_device_authorized
             SET current_ad_id = ' . ($newAdId ? intval($newAdId) : 'NULL') . ',
                 ad_expires_at = "' . $expiresAt . '"
             WHERE device_uuid = "' . mysqli_real_escape_string($this->DB->db, $deviceUuid) . '"'
        );

        // 5. Return data and log initial hit if we have an ad
        if ($newAdId) {
            return $this->getAdData($newAdId, $unitId, true); // Log hit only on the first display of the window
        }

        return null;
    }

    # ...................................................................
    /**
     * Internal helper to fetch full ad data by ID and optionally log a hit.
     */
    private function getAdData(int $adId, int $unitId, bool $logHit = false): array|null {
        // Optimization: Used getNoCalcRows to avoid expensive SQL_CALC_FOUND_ROWS overhead
        $ad = $this->getNoCalcRows(['ad.id = ' . intval($adId), 'ad.status = "active"'], null, 1);

        if (empty($ad)) {
            return null;
        }

        $data = $ad[0];

        if ($data['target_link']) {
            $baseUrl = \Janmensik\Jmlib\AppData::getInstance()->getData('BASE_URL') ?: '';
            $redirectUrl = rtrim($baseUrl, '/') . '/goto/ad/' . $adId;

            if (!empty($data['qr_code_svg'])) {
                $data['qr_code_data'] = $data['qr_code_svg'];
            } else {
                $options = new \chillerlan\QRCode\QROptions([
                    'version'      => \chillerlan\QRCode\Common\Version::AUTO,
                    'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
                    'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::L,
                    'addQuietzone' => true,
                    'svgViewBox'   => true,
                ]);

                $data['qr_code_data'] = (new \chillerlan\QRCode\QRCode($options))->render($redirectUrl);

                // Save to cache
                $this->DB->query('UPDATE advert SET qr_code_svg = "' . mysqli_real_escape_string($this->DB->db, $data['qr_code_data']) . '" WHERE id = ' . intval($adId));
            }
            unset($data['qr_code_svg']); // Clean up array before returning
        }

        if ($logHit) {
            $this->setAdHit($unitId, $adId);
        }

        return $data;
    }

    # ...................................................................
    public function getAd(int $unit_id): array|null {
        # Conditions: Only Active ads
        $where = array('ad.status="active"');

        $ad = $this->getRandom($where, 8, 10, null, 1);

        if (!empty($ad)) {
            return $this->getAdData($ad[0]['id'], $unit_id, true);
        }

        return null;
    }

    # ...................................................................
    public function setAdHit(int $unit_id, int $advert_id): void {
        # add advert view +1 hit
        $this->DB->query('INSERT INTO advert_hit (advert_id, unit_id, display_count) VALUES ("' . $advert_id . '", "' . $unit_id . '", 1) ON DUPLICATE KEY UPDATE display_count = display_count + 1, last_displayed_at = CURRENT_TIMESTAMP;');
    }

    # ...................................................................
    /**
     * Log a link click (redirection) hit.
     */
    public function logLinkHit(int $adId): void {
        // Since we don't have unit_id context in the goto link easily without extra params,
        // we update the global counter if we use a simplified schema,
        // but the requirements say advert_hit table which is per-unit.
        // For now, we update the first unit record or implement a general counter.
        // Given the prompt "it will count into SQL table advert_hit.link_count +1",
        // and that table uses (advert_id, unit_id) as key, I'll update all units for this ad
        // or just ensure we have at least one record.
        $this->DB->query('UPDATE advert_hit SET link_count = link_count + 1 WHERE advert_id = ' . intval($adId));
    }

    # ...................................................................
    public function validate(): array {
        $errors = [];

        # status
        if (empty($this->data['status'])) {
            $errors['status'] = "Status is required";
        }

        return $errors;
    }
}
