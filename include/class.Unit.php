<?php

namespace PozarniPoplach;

use Janmensik\Jmlib\Modul;
use Janmensik\Jmlib\Database;

class Unit extends Modul {
    protected ?string $sql_base = 'SELECT SQL_CALC_FOUND_ROWS ut.id, ut.status, ut.fullname, ut.registration, ut.pincode, ut.category, ut.region_id, ut.base_latitude, ut.base_longitude, reg.RZPK AS region_rzpk, reg.title AS region_title, ut.calendar_url FROM unit ut JOIN region reg ON ut.region_id=reg.id GROUP BY ut.id'; # zaklad SQL dotazu
    protected ?string $sql_update = 'UPDATE unit ut'; # zaklad SQL dotazu - UPDATE
    protected ?string $sql_insert = 'INSERT INTO unit'; # zaklad SQL dotazu - INSERT
    protected ?string $sql_table = 'ut';
    protected int|string $order = 3;

    //protected ?array $fulltext_columns = array('ut.id', 'ut.fullname', 'ut.registration', 'ut.pincode');
    protected int $limit = -1;

    public array $text = array(
        'cs' => array(
            'status' =>
            array('ok' => 'Aktivní', 'paused' => 'Pozastaveno', 'deleted' => 'Smazaná')
        )
    );

    protected array $elements = [
        'status',
        'fullname',
        'registration',
        'pincode',
        'category',
        'region_id',
        'base_latitude',
        'base_longitude',
        'calendar_url'
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
        $ad = $this->getNoCalcRows(['ad.id = ' . intval($adId)], null, 1);

        if (empty($ad)) {
            return null;
        }

        $data = $ad[0];

        if ($data['target_link']) {
            $options = new \chillerlan\QRCode\QROptions([
                'version'      => \chillerlan\QRCode\Common\Version::AUTO,
                'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
                'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::L,
                'addQuietzone' => true,
                'svgViewBox'   => true,
            ]);

            $data['qr_code_data'] = (new \chillerlan\QRCode\QRCode($options))->render($data['target_link']);
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
    public function getRegions(): array|null {
        $regions = $this->DB->getAllRows($this->DB->query(
            'SELECT id, RZPK, title FROM region ORDER BY title ASC',
            'get_regions'
        ));

        return $regions ?: null;
    }

    # ...................................................................
    public function validate(): array {

        $errors = [];

        # fullname
        if (empty($this->data['fullname'])) {
            $errors['fullname'] = "Fullname is required";
        }
        # registration
        if (empty($this->data['registration'])) {
            $errors['registration'] = "Registration is required";
        }
        # category
        if (empty($this->data['category'])) {
            $errors['category'] = "Category is required";
        }

        return $errors;
    }
}
