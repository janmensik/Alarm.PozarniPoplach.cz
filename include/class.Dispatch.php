<?php

namespace PozarniPoplach;

use Janmensik\Jmlib\Modul;
use Janmensik\Jmlib\Database;
use DOMDocument;
use DOMXPath;
use RuntimeException;

class Dispatch extends Modul {
    protected $sql_base = 'SELECT SQL_CALC_FOUND_ROWS dis.*, UNIX_TIMESTAMP(dis.received) AS received_ts, UNIX_TIMESTAMP(dis.dispatched_at) AS dispatched_at_ts, u.fullname AS unit_fullname, u.registration AS unit_registration, u.category AS unit_category, u.base_latitude, u.base_longitude, et.name AS event_name, et.icon AS event_icon, ets.name AS event_subtype_name, ets.icon AS event_subtype_icon, IF(ets.icon IS NOT NULL, ets.icon, et.icon) AS icon, reg.title AS region_title, reg.rzpk AS region_rzpk FROM dispatch dis LEFT JOIN event_type et ON dis.event_id = et.id LEFT JOIN event_type ets ON dis.event_subtype_id = ets.id LEFT JOIN region reg ON dis.address_region_id = reg.id LEFT JOIN unit u ON dis.unit_id = u.id WHERE 1 GROUP BY dis.id'; # zaklad SQL dotazu
    protected $sql_update = 'UPDATE dispatch dis'; # zaklad SQL dotazu - UPDATE
    protected $sql_insert = 'INSERT INTO dispatch'; # zaklad SQL dotazu - INSERT
    protected $sql_table = 'dis';
    protected $order = '-2'; // Order by received time descending
    protected $limit = -1;
    protected $fulltext_columns = array('dis.event', 'dis.event_subtype', 'dis.address_fulltext', 'dis.situation');

    protected $many_to_many = array(
        'unit_vehicles' => array(
            'table' => 'dispatch_unit_vehicle',
            'main_key' => 'dispatch_id',
            'columns' => ['unit_vehicle_id', 'fullname'],
        ),
        'other_vehicles' => array(
            'table' => 'dispatch_other_vehicle',
            'main_key' => 'dispatch_id',
            'columns' => ['unit', 'vehicle_type_id', 'vehicle', 'callsign', 'fullname', 'unit_type'],
        ),
    );

    protected $email_address_pattern = '/notifikace\.([A-Z0-9]{6})@pozarnipoplach\.cz/i';

    public $cache;

    // Private properties for caching static data
    private $vehicle_types;
    private $regions;
    private $event_types;

    # ...................................................................
    public function __construct(Database &$database) {
        parent::__construct($database);
    }

    # ...................................................................
    /**
     * identify unit by its pincode
     * @param int|null $unit_id Unit's ID to check.
     * @return array|null Return full dispatch data of a random dispatch for the given unit or any unit if null is provided, or null if not found.
     *
     */
    public function getRandomDispatch(int|null $unit_id = null): array|null {
        return ($this->getDispatch($this->findRandomId($unit_id ? 'unit_id = "' . mysqli_real_escape_string($this->DB->db, trim($unit_id)) . '"' : null)));

        return null;
    }

    # ...................................................................
    /**
     * identify unit by its pincode
     * @param int|null $unit_id Unit's ID to check.
     * @return array|null Return full dispatch data of the last dispatch for the given unit or any unit if null is provided, or null if not found.
     *
     */
    public function getLastDispatch(int|null $unit_id = null): array|null {
        $data = $this->get(($unit_id ? 'dis.unit_id = "' . intval($unit_id) . '"' : null), 'dis.dispatched DESC', 1, null, true);

        if (!empty($data) && is_array($data) && !empty($data[0]) && is_array($data[0]) && !empty($data[0]['id'])) {
            return $this->getDispatch(intval($data[0]['id']));
        }

        return null;
    }

    # ...................................................................
    /**
     * Return full complete dispatch data by its ID
     * @param int|null $dispatch_id Unit's ID to check.
     * @return array|null Full dispatch data for provided ID.
     *
     */
    public function getDispatch(int|null $dispatch_id = null): array|null {
        if (empty($dispatch_id)) {
            return null;
        }

        // load the most recent (last) dispatch
        $data = $this->getId(intval($dispatch_id));

        if (!empty($data) && is_array($data)) {
            // load unit vehicles
            // TBD
            $data['unit_vehicles'] = $this->DB->getAllRows($this->DB->query('SELECT duv.fullname, uv.*, vt.type AS vehicle_type, vt.code AS vehicle_type_code, vt.icon AS vehicle_type_icon FROM dispatch_unit_vehicle duv LEFT JOIN unit_vehicle uv ON uv.id = duv.unit_vehicle_id LEFT JOIN vehicle_type vt ON uv.vehicle_type_id = vt.id WHERE duv.dispatch_id = "' . mysqli_real_escape_string($this->DB->db, trim($data['id'])) . '"', __METHOD__ . ' get Unit vehicles for dispatch'));

            // load other vehicles
            // TBD
            $data['other_vehicles'] = $this->DB->getAllRows($this->DB->query('SELECT dov.*, vt.type AS vehicle_type, vt.code AS vehicle_type_code, vt.icon AS vehicle_type_icon FROM dispatch_other_vehicle dov LEFT JOIN vehicle_type vt ON dov.vehicle_type_id = vt.id WHERE dov.dispatch_id = "' . mysqli_real_escape_string($this->DB->db, trim($data['id'])) . '"', __METHOD__ . ' get Other vehicles for dispatch'));

            return $data;
        }

        return null; // Return null if no match is found
    }

    # ...................................................................
    /**
     * parse and beautify last dispatch data (remove unnecessary fields, link ENUMs, prepare to be shown)
     * @param array|null $dispatch Array of complete dispatch data from getLastDispatch.
     * @return array|null dispatch data ready to be shown or null if input is empty.
     *
     */
    public function beautifulLastDispatch(array|null $dispatch): array|null {
        // global $LOCAL;

        if (empty($dispatch) || !is_array($dispatch)) {
            return null;
        }

        // unit name
        if (!empty($dispatch['unit_fullname'])) {
            $dispatch['unit'] = $dispatch['unit_fullname'];
            unset($dispatch['unit_fullname']);
        }

        // event icon
        if (!empty($dispatch['event_subtype_icon'])) {
            $dispatch['event_icon'] = $dispatch['event_subtype_icon'];
            unset($dispatch['event_subtype_icon']);
        } elseif (empty($dispatch['event_icon'])) {
            $dispatch['event_icon'] = 'fa-solid fa-light-emergency-on';
        }

        // event name and subtype name
        if (!empty($dispatch['event_name'])) {
            $dispatch['event'] = $dispatch['event_name'];
            unset($dispatch['event_name']);
        }
        if (!empty($dispatch['event_subtype_name'])) {
            $dispatch['event_subtype'] = $dispatch['event_subtype_name'];
            unset($dispatch['event_subtype_name']);
        }

        // city_part only if different from city
        if (!empty($dispatch['address_city_part']) && !empty($dispatch['address_city']) && mb_strtolower($dispatch['address_city_part']) == mb_strtolower($dispatch['address_city'])) {
            $dispatch['address_city_part'] = null;
        }

        // unit vehicles
        if (!empty($dispatch['unit_vehicles']) && is_array($dispatch['unit_vehicles'])) {
            foreach ($dispatch['unit_vehicles'] as $key => $vehicle) {
                if (empty($vehicle['vehicle_type_icon'])) {
                    $dispatch['unit_vehicles'][$key]['vehicle_type_icon'] = 'fa-solid fa-question'; // default icon
                }
                if (empty($vehicle['name']) && !empty($vehicle['fullname'])) {
                    $dispatch['unit_vehicles'][$key]['name'] = $vehicle['fullname'];
                }
            }
        }

        // get directions
        $dispatch['directions'] = $this->googleMapsDirection($dispatch['base_latitude'], $dispatch['base_longitude'], $dispatch['gps_latitude'], $dispatch['gps_longitude']);

        // maps and streetview sizes definitions
        $display_width = 640;
        $display_height_map = 268; // 2.39:1
        $display_height_view = 150; // 4.25:1

        // find out if streetview is available
        $streetview_url = 'https://maps.googleapis.com/maps/api/streetview/metadata?location=' . $dispatch['gps_latitude'] . ',' . $dispatch['gps_longitude'] . '&key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&fov=120&return_error_code=true';
        $streetview_json = @file_get_contents($streetview_url);
        $streetview_data = null;
        if ($streetview_json !== false) {
            $streetview_data = json_decode($streetview_json, true);
        }
        if ($streetview_data['status'] === 'OK') {
            $dispatch['streetview_available'] = true;

            # Google Maps Static Streetview URL
            $dispatch['directions']['static_streetview'] = 'https://maps.googleapis.com/maps/api/streetview?size=' . ($display_width * 2) . 'x' . ($display_height_view * 2) . '&location=' . $dispatch['gps_latitude'] . ',' . $dispatch['gps_longitude'] . '&key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&fov=120';
        } else {
            $dispatch['streetview_available'] = false;

            # Mapbox Static map URL
            $dispatch['directions']['static_streetview'] = 'https://api.mapbox.com/styles/v1/mapbox/satellite-streets-v12/static/pin-s+e44b38(' . $dispatch['gps_longitude'] . ',' . $dispatch['gps_latitude'] . ')/' . $dispatch['gps_longitude'] . ',' . $dispatch['gps_latitude'] . ',17,0/' . $display_width . 'x' . $display_height_view . '@2x?access_token=' . $_ENV['MAPBOX_API_KEY'];
        }

        # Google Maps Static URL
        $dispatch['directions']['static_big_map'] = 'https://maps.googleapis.com/maps/api/staticmap?size=' . $display_width . 'x' . $display_height_map . '&scale=2&markers=color:red|' . $dispatch['gps_latitude'] . ',' . $dispatch['gps_longitude'] . '&key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . ($dispatch['directions']['polyline'] ? '&path=color:0x0000ff|weight:5|enc:' . $dispatch['directions']['polyline'] : '');

        // remove plaindata
        unset($dispatch['plaindata']);

        return $dispatch;
    }

    # ...................................................................
    /**
     * parse and beautify last dispatch data (remove unnecessary fields, link ENUMs, prepare to be shown)
     * @param string|null $origin_latitude Origin latitude.
     * @param string|null $origin_longitude Origin longitude.
     * @param string|null $destination_latitude Destination latitude.
     * @param string|null $destination_longitude Destination longitude.
     * @return array|null summary and polyline for direction
     *
     */
    private function googleMapsDirection(string|null $origin_latitude = null, string|null $origin_longitude = null, string|null $destination_latitude = null, string|null $destination_longitude = null): array|null {
        // global $LOCAL;

        if (empty($origin_latitude) || empty($origin_longitude) || empty($destination_latitude) || empty($destination_longitude) || empty($_ENV['GOOGLE_MAPS_API_KEY'])) {
            return null;
        }

        $params = http_build_query([
            'origin' => $origin_latitude . ',' . $origin_longitude,
            'destination' => $destination_latitude . ',' . $destination_longitude,
            'mode' => 'driving',
            'key' => $_ENV['GOOGLE_MAPS_API_KEY'],
            'alternatives' => 'false',
            'avoid' => 'ferries'
        ]);
        $url = "https://maps.googleapis.com/maps/api/directions/json?$params";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FAILONERROR => false,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            throw new RuntimeException("cURL error: $err");
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            return null;
            // throw new RuntimeException("HTTP error: $httpCode");
        }

        $data = json_decode($resp, true);
        if (!isset($data['status'])) {
            return null;
            // throw new RuntimeException("Unexpected response from Directions API");
        }
        if ($data['status'] !== 'OK') {
            // Typical statuses: ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED, INVALID_REQUEST
            return null;
            // throw new RuntimeException("Directions API error: " . $data['status'] . ' - ' . ($data['error_message'] ?? ''));
        }

        if (empty($data['routes'])) {
            return null;
            // throw new RuntimeException("No routes found");
        }

        // all good
        // Sum distance/duration over legs (works for routes with multiple legs)
        $totalDistanceMeters = 0;
        $totalDurationSeconds = 0;
        $route = $data['routes'][0];
        if (!empty($route['legs'])) {
            foreach ($route['legs'] as $leg) {
                if (isset($leg['distance']['value'])) {
                    $totalDistanceMeters += (int)$leg['distance']['value'];
                }
                if (isset($leg['duration']['value'])) {
                    $totalDurationSeconds += (int)$leg['duration']['value'];
                }
            }
        }
        $output['distance'] = ceil($totalDistanceMeters / 100) / 10; // in km
        $output['duration'] = ceil($totalDurationSeconds / 60); // in minutes
        $output['polyline'] = $data['routes'][0]['overview_polyline']['points'];

        return $output;
    }

    # ...................................................................
    /**
     * identify unit by its pincode
     * @param string|null $unit_pincode Unit's pincode to check.
     * @param bool|null $hashed is PINCODE hashed (SHA1) or plain text
     * @return int|null Unit's ID or null if not found.
     *
     */
    public function checkUnitPincode(string|null $unit_pincode, bool|null $hashed = false): int|null {
        if (empty($unit_pincode)) {
            return null; // Return null if the input is empty
        }

        $pincode_format = $hashed ? 'SHA1(u.pincode)' : 'u.pincode';

        $unit_id = $this->DB->getResult($this->DB->query('SELECT u.id FROM unit u WHERE ' . $pincode_format . ' = "' . mysqli_real_escape_string($this->DB->db, trim($unit_pincode)) . '" LIMIT 1', __METHOD__ . ' get Unit id by pincode'));

        if (!empty($unit_id)) {
            return $unit_id;
        }
        return null; // Return null if no match is found
    }

    # ...................................................................
    /**
     * extract unit registration number from email address(es)
     * @param array|string|null $email_addresses Array of email addresses or a single email.
     * @return string|null Unit's registration number or null if not found.
     *
     */
    public function extractUnitRegistration(array|string|null $email_addresses): string|null {
        if (empty($email_addresses)) {
            return null; // Return null if the input array is empty
        } elseif (!is_array($email_addresses)) {
            $email_addresses = array($email_addresses); // Convert to array if it's a single string
        }

        foreach ($email_addresses as $email) {
            if (preg_match($this->email_address_pattern, $email, $matches)) {
                return $matches[1]; // Return the first match found
            }
        }
        return null; // Return null if no match is found
    }

    # ...................................................................
    /**
     * Parse 1 dispatch HTML email and parse it into array
     * @param string $htmlContent The HTML content of the dispatch email.
     * @return array Parsed data from the dispatch email.
     *
     */
    public function parseDispatchHtml(string $htmlContent): array {
        // The HTML is not well-formed, so we suppress warnings from the parser.
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        // Prepending the XML encoding declaration helps DOMDocument handle UTF-8 characters correctly.
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        /*
        $default_keys = [
            'event',
            'event_sub',
            // 'received',
            'unit',
            // 'unit_id',
            // 'event_id',
            // 'event_subtype_id',
            'address' => ['region', 'city', 'district', 'city_part', 'street', 'house_number', 'gps', 'gps_latitude', 'gps_longitude'],
            'object',
            'object_description',
            'clarification',
            'situation',
            // 'links' => ['google_maps', 'mapy_cz'],
            'notifier',
            'notifier_phone',
            'unit_vehicles' => [],
            'other_vehicles' => [],
            'info',
            'dispatch_id',
            'dispatched_by',
            'dispatched_at'
        ];

        // Initialize the $data array with the default structure and null values.
        $data = [];
        foreach ($default_keys as $key => $value) {
            if (is_string($key)) {
                $data[$key] = is_array($value) ? array_fill_keys($value, null) : [];
            } else {
                $data[$value] = null;
            }
        }
        */

        /**
         * Helper function to execute an XPath query and return the trimmed text content of the first result.
         * It cleans up whitespace and non-breaking spaces.
         * @param string $query The XPath query string.
         * @return string|null The cleaned text content or null if not found.
         */
        $queryValue = function (string $query) use ($xpath): ?string {
            $node = $xpath->query($query)->item(0);
            if ($node) {
                // \xC2\xA0 is the UTF-8 non-breaking space (&nbsp;)
                return trim($node->textContent, " \t\n\r\0\x0B\xC2\xA0");
            }
            return null;
        };

        $data['plaindata'] = $htmlContent;

        // --- Extraction Logic ---

        // Location Header (first text node in the main div)
        $data['unit'] = $queryValue('//div/text()[1]');

        // Event Type
        $data['event'] = $queryValue('//div/b/big');

        if ($data['event']) {
            preg_match('/(.*) - (.*)/u', $data['event'], $event_parts);
            if (is_array($event_parts) && count($event_parts) === 3) {
                $data['event'] = trim($event_parts[1]);
                $data['event_sub'] = trim($event_parts[2]);
            }
        }

        // Address Details
        $data['address']['region'] = $queryValue('//text()[contains(., "KRAJ:")]/following-sibling::b[1]');

        // city and District are on the same line, but district is outside the <b> tag.
        $cityNode = $xpath->query('//text()[contains(., "OBEC:")]/following-sibling::b[1]')->item(0);
        if ($cityNode) {
            $data['address']['city'] = trim($cityNode->textContent);

            // The district info is in the next text node sibling
            $districtNode = $cityNode->nextSibling;
            if ($districtNode && $districtNode->nodeType === XML_TEXT_NODE) {
                // Extract text from within the parentheses, e.g., "(okr.: Praha-západ)"
                if (preg_match('/\(okr\.:\s*(.*?)\)/', $districtNode->textContent, $matches)) {
                    $data['address']['district'] = trim($matches[1]);
                }
            }
        }

        $data['address']['city_part'] = $queryValue('//text()[contains(translate(., "Č", "C"), "CÁST:")]/following-sibling::b[1]');
        $data['address']['street'] = $queryValue('//text()[contains(., "ULICE:")]/following-sibling::b[1]');
        $data['address']['house_number'] = $queryValue('//text()[contains(., "Č.P.:") or contains(., "C.P.:")]/following-sibling::b[1]');
        $data['address']['gps'] = $queryValue('//text()[contains(., "GPS:")]/following-sibling::b[1]');

        // detailed GPS parsing (if available)
        if ($data['address']['gps']) {
            if (preg_match('/(\d+\.\d+)\s*[NS]?,\s*(\d+\.\d+)\s*[EW]?/', $data['address']['gps'], $matches)) {
                $data['address']['gps_latitude'] = $matches[1];
                $data['address']['gps_longitude'] = $matches[2];
            }
        }

        // Object Description
        $data['object_description'] = $queryValue('//text()[contains(., "OBJEKT:")]/following-sibling::b[1]');

        // Clarification
        $data['clarification'] = $queryValue('//text()[contains(., "UPŘESNĚNÍ:") or contains(., "UPRESNENÍ:")]/following-sibling::br[1]/following-sibling::b[1]');

        // What Happened
        $data['situation'] = $queryValue('//text()[contains(., "CO SE STALO:")]/following-sibling::br[1]/following-sibling::b[1]');

        // Map Links
        // $data['links']['google_maps'] = $queryValue('//a[text()="Google mapa"]/@href');
        // $data['links']['mapy_cz'] = $queryValue('//a[text()="Mapy.cz"]/@href');

        // Notifier
        $data['notifier'] = $queryValue('//text()[contains(., "OZNÁMIL:")]/following-sibling::b[1]');

        // Notification Phone
        $data['notifier_phone'] = $queryValue('//text()[contains(., "Telefon:")]/following-sibling::b[1]');

        // Footer Info
        $footerInfo = $queryValue('//small/i');
        if ($footerInfo) {
            $data['info'] = $footerInfo;
            if (preg_match('/Událost [čc]\. (\d+) - odbavil (.+) - (\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2})/u', $footerInfo, $matches)) {
                $data['dispatch_id'] = $matches[1];
                $data['dispatched_by'] = $matches[2];
                $data['dispatched_at'] = $matches[3];
            }
        }

        // Unit Vehicles (can be a list)
        $unitVehiclesNode = $xpath->query('//text()[contains(., "TECHNIKA ' . $data['unit'] . ':")]/following-sibling::big[1]')->item(0);
        if ($unitVehiclesNode) {
            $innerHTML = '';
            foreach ($unitVehiclesNode->childNodes as $child) {
                $innerHTML .= $child->ownerDocument->saveHTML($child);
            }
            // Split the HTML content by <br> tags
            $vehicleLinesHTML = preg_split('/<br\s*\/?>/i', $innerHTML);
            $unit_vehicles = array_values(array_filter(array_map(function ($line) {
                // For each line, strip any remaining HTML tags and trim whitespace
                return trim(strip_tags($line));
            }, $vehicleLinesHTML)));
        }
        if (!$unitVehiclesNode || empty($unit_vehicles)) {
            $data['unit_vehicles'] = null;
        } else {
            foreach ($unit_vehicles as $key => $value) {
                $data['unit_vehicles'][$key]['fullname'] = $value;
                $matches = [];
                if (preg_match('/(([a-z]{2,4}) )?(.*) - ([a-z]{3} [0-9]{3})/i', $value, $matches)) {
                    $data['unit_vehicles'][$key]['vehicle_type_code'] = $matches[2];
                    $data['unit_vehicles'][$key]['callsign'] = $matches[4];
                }
            }
        }

        // Other Vehicles (a list of vehicles from other units)
        $otherVehiclesNode = $xpath->query('//i[contains(., "TECHNIKA dalších jednotek PO:")]/following-sibling::big[1]')->item(0);
        if ($otherVehiclesNode) {
            $innerHTML = '';
            foreach ($otherVehiclesNode->childNodes as $child) {
                $innerHTML .= $child->ownerDocument->saveHTML($child);
            }
            // Split the HTML content by <br> tags
            $vehicleLinesHTML = preg_split('/<br\s*\/?>/i', $innerHTML);
            $vehiclesList = array_map(function ($line) {
                // For each line, strip any remaining HTML tags and trim whitespace
                return trim(strip_tags($line));
            }, $vehicleLinesHTML);

            // Filter out empty lines and the common ":  - " placeholder
            $other_vehicles = array_values(array_filter($vehiclesList, function ($line) {
                return !empty($line) && trim(strip_tags($line)) !== ':  -';
            }));
        }
        if (!$otherVehiclesNode || empty($other_vehicles)) {
            $data['other_vehicles'] = null;
        } else {
            foreach ($other_vehicles as $key => $value) {
                $data['other_vehicles'][$key]['fullname'] = $value;
                $matches = [];
                if (preg_match('/(.*?): (([a-z]{2,4}) )?(.*) - ([a-z]{3} [0-9]{3})/i', $value, $matches)) {
                    $data['other_vehicles'][$key]['unit'] = $matches[1];
                    $data['other_vehicles'][$key]['vehicle_type_code'] = $matches[3];
                    $data['other_vehicles'][$key]['callsign'] = $matches[5];
                    $data['other_vehicles'][$key]['vehicle'] = $matches[2] . $matches[4];
                } elseif (preg_match('/(.*?): (.*)/', $value, $matches)) {
                    $data['other_vehicles'][$key]['unit'] = $matches[1];
                    $data['other_vehicles'][$key]['vehicle'] = $matches[2];
                }
            }
        }

        return self::arrayFilterTrimRecursive($data);
    }

    # ...................................................................
    /**
     * Recursively filters an array, removing any "empty" values from all levels and trim strings.
     * An "empty" value is anything that evaluates to false (e.g., '', 0, null, false, []).
     *
     * @param array $array The array to filter.
     * @return array The filtered array.
     */
    private static function arrayFilterTrimRecursive(array $array): array {
        foreach ($array as $key => &$value) { // Use a reference to modify the array in place
            if (is_array($value)) {
                // If the value is an array, recurse into it
                $value = self::arrayFilterTrimRecursive($value);
            }
        }

        // Trim strings in the current level of the array
        $array = array_map(function ($item) {
            return is_string($item) ? mb_trim($item) : $item;
        }, $array);

        // Filter the current level of the array for non-empty values
        return array_filter($array);
    }

    # ...................................................................
    /**
     * add links to ENUMs data for parser dispatch
     * @param array $data The structured data array from parseDispatchHtml.
     * @param string|null $unit_registration Optional unit registration number to help identify the unit.
     * @return array Parsed data from the dispatch with ENUMs linked.
     *
     */
    public function linkParsedDispatch(array $data, string|null $unit_registration = null): array {
        // Lazy load vehicle types only if they haven't been loaded yet.
        if (empty($this->vehicle_types)) {
            $this->vehicle_types = $this->DB->getAllRows($this->DB->query('SELECT vt.id, vt.code, vt.type, vt.icon FROM vehicle_type vt', __METHOD__ . ' get Vehicle types'), 'code');
        }

        // Lazy load regions only if they haven't been loaded yet.
        if (empty($this->regions)) {
            $this->regions = $this->DB->getAllRows($this->DB->query('SELECT reg.id, reg.rzpk, reg.title FROM region reg', __METHOD__ . ' get Regions'), 'rzpk');
        }

        // Lazy load event types only if they haven't been loaded yet.
        if (empty($this->event_types)) {
            $this->event_types = $this->DB->getAllRows($this->DB->query('SELECT et.id, et.name, et.icon, et.level, et.parent_id FROM event_type et', __METHOD__ . ' get Event types'), 'id');
        }

        // Find region by name (title)
        if (!empty($data['address']['region']) && !empty($this->regions)) {
            foreach ($this->regions as $region) {
                if (mb_stripos($data['address']['region'], $region['title']) !== false) {
                    $data['address']['region_id'] = $region['id'];
                    $data['address']['region_rzpk'] = $region['rzpk'];
                    $data['address']['region_name'] = $region['title'];
                    break;
                }
            }
        }

        // Find Event Type and Subtype by name
        if (!empty($data['event']) && !empty($this->event_types)) {
            foreach ($this->event_types as $event_type) {
                if ($event_type['parent_id'] === null && mb_stripos($data['event'], $event_type['name']) !== false) {
                    $data['event_type']['id'] = $event_type['id'];
                    $data['event_type']['name'] = $event_type['name'];
                    $data['event_type']['icon'] = $event_type['icon'];
                    $data['event_type']['level'] = $event_type['level'];
                    // break;
                } elseif ($event_type['parent_id'] !== null && !empty($data['event_sub']) && mb_stripos($data['event_sub'], $event_type['name']) !== false) {
                    $data['event_subtype']['id'] = $event_type['id'];
                    $data['event_subtype']['name'] = $event_type['name'];
                    $data['event_subtype']['icon'] = $event_type['icon'];
                    $data['event_subtype']['level'] = $event_type['level'];
                    $data['event_subtype']['parent_id'] = $event_type['parent_id'];
                    // break;
                }
            }
            if (!empty($data['event_subtype']['parent_id']) && empty($data['event_type']['id']) && $data['event_subtype']['parent_id'] != $data['event_type']['id']) {
                $data['event_subtype']['parent_mismatch'] = true; // Mark potential mismatch if subtype's parent doesn't match the found event type
            }
        }

        // Find unit unit
        if (!empty($data['unit'])) {
            $unit = $this->DB->getRow($this->DB->query('SELECT u.* FROM unit u WHERE u.fullname = "' . mysqli_real_escape_string($this->DB->db, trim($data['unit']))  . '"' . ($unit_registration ? ' AND u.registration = "' . mysqli_real_escape_string($this->DB->db, trim($unit_registration)) . '"' : '') . ' LIMIT 1', __METHOD__ . ' get Unit'));

            if (isset($unit)) {
                $data['unit_id'] = $unit['id'];
                $data['unit'] = $unit['fullname'];
            }
        }

        // Link unit vehicles
        if (!empty($data['unit_vehicles'])) {
            foreach ($data['unit_vehicles'] as $key => $vehicle) {
                // Link vehicle type
                if (!empty($vehicle['vehicle_type_code']) && isset($this->vehicle_types[$vehicle['vehicle_type_code']])) {
                    $data['unit_vehicles'][$key]['vehicle_type_id'] = $this->vehicle_types[$vehicle['vehicle_type_code']]['id'];
                    $data['unit_vehicles'][$key]['vehicle_type'] = $this->vehicle_types[$vehicle['vehicle_type_code']]['type'];
                    $data['unit_vehicles'][$key]['vehicle_type_icon'] = $this->vehicle_types[$vehicle['vehicle_type_code']]['icon'];
                }

                // Link Unit vehicle ID if exists
                if (!empty($data['unit_id']) && !empty($vehicle['callsign'])) {
                    $unit_vehicle = $this->DB->getRow($this->DB->query('SELECT uv.* FROM unit_vehicle uv WHERE uv.unit_id = "' . mysqli_real_escape_string($this->DB->db, trim($data['unit_id']))  . '" AND uv.callsign = "' . mysqli_real_escape_string($this->DB->db, trim($vehicle['callsign'])) . '" LIMIT 1', __METHOD__ . ' get Unit vehicle'));

                    if (!empty($unit_vehicle) && is_array($unit_vehicle) && is_array($data['unit_vehicles'][$key])) {
                        $data['unit_vehicles'][$key]['unit_vehicle_id'] = $unit_vehicle['id'];
                        $data['unit_vehicles'][$key]['callsign'] = $unit_vehicle['callsign'];
                        $data['unit_vehicles'][$key]['name'] = $unit_vehicle['name'];
                        $data['unit_vehicles'][$key]['vehicle_type_id'] = $unit_vehicle['vehicle_type_id']; // Override vehicle type ID if linked from unit vehicle
                    }
                }
            }
        }

        // Link other vehicles
        if (!empty($data['other_vehicles'])) {
            foreach ($data['other_vehicles'] as $key => $vehicle) {
                // Link vehicle type if exists
                if (!empty($vehicle['vehicle_type_code']) && isset($this->vehicle_types[$vehicle['vehicle_type_code']])) {
                    $data['other_vehicles'][$key]['vehicle_type_id'] = $this->vehicle_types[$vehicle['vehicle_type_code']]['id'];
                    $data['other_vehicles'][$key]['vehicle_type'] = $this->vehicle_types[$vehicle['vehicle_type_code']]['type'];
                    $data['other_vehicles'][$key]['vehicle_type_icon'] = $this->vehicle_types[$vehicle['vehicle_type_code']]['icon'];
                }
            }
        }
        return $data;
    }

    # ...................................................................
    /**
     * Saves a parsed and linked dispatch record to the database.
     *
     * @param array $data The structured data array from linkParsedDispatch.
     * @return array|false The ID of the newly inserted dispatch, or false on failure.
     */
    public function prepareSave(array $data): array|false {
        // Basic check for essential data
        if (empty($data['dispatch_id'])) {
            return false;
        }

        // Prepare data for insertion
        $set['unit_id'] = isset($data['unit_id']) ? '"' . $this->sanitize($data['unit_id'], 'int') . '"' : 'NULL';
        $set['received'] = 'NOW()';
        $set['plaindata'] = "'" . $this->sanitize($data['plaindata']) . "'";
        if (!empty($data['unit'])) {
            $set['unit'] = '"' . $this->sanitize($data['unit']) . '"';
        }
        $set['event_id'] = isset($data['event_type']['id']) ? '"' . $this->sanitize($data['event_type']['id'], 'int') . '"' : 'NULL';
        $set['event_subtype_id'] = isset($data['event_subtype']['id']) ? '"' . $this->sanitize($data['event_subtype']['id'], 'int') . '"' : 'NULL';
        if (!empty($data['event'])) {
            $set['event'] = '"' . $this->sanitize($data['event']) . '"';
        }
        if (!empty($data['event_sub'])) {
            $set['event_subtype'] = '"' . $this->sanitize($data['event_sub']) . '"';
        }
        $set['address_region_id'] = isset($data['address']['region_id']) ? '"' . $this->sanitize($data['address']['region_id'], 'int') . '"' : 'NULL';
        if (!empty($data['address']['city'])) {
            $set['address_city'] = '"' . $this->sanitize($data['address']['city']) . '"';
        }
        if (!empty($data['address']['city_part'])) {
            // This was a copy-paste error, it should be address_city_part
            $set['address_city_part'] = '"' . $this->sanitize($data['address']['city_part']) . '"';
        }
        if (!empty($data['address']['district'])) {
            $set['address_district'] = '"' . $this->sanitize($data['address']['district']) . '"';
        }
        if (!empty($data['address']['street'])) {
            $set['address_street'] = '"' . $this->sanitize($data['address']['street']) . '"';
        }
        if (!empty($data['address']['house_number'])) {
            $set['address_house_number'] = '"' . $this->sanitize($data['address']['house_number']) . '"';
        }
        $set['gps_latitude'] = isset($data['address']['gps_latitude']) ? '"' . $this->sanitize($data['address']['gps_latitude'], 'float') . '"' : 'NULL';
        $set['gps_longitude'] = isset($data['address']['gps_longitude']) ? '"' . $this->sanitize($data['address']['gps_longitude'], 'float') . '"' : 'NULL';
        if (!empty($data['object'])) {
            $set['object'] = '"' . $this->sanitize($data['object']) . '"';
        }
        if (!empty($data['object_clarification'])) {
            $set['object_clarification'] = '"' . $this->sanitize($data['object_clarification']) . '"';
        }
        if (!empty($data['situation'])) {
            $set['situation'] = '"' . $this->sanitize($data['situation']) . '"';
        }
        if (!empty($data['notifier'])) {
            $set['notifier'] = '"' . $this->sanitize($data['notifier']) . '"';
        }
        if (!empty($data['notifier_phone'])) {
            $set['notifier_phone'] = '"' . $this->sanitize($data['notifier_phone']) . '"';
        }
        if (!empty($data['info'])) {
            $set['info'] = '"' . $this->sanitize($data['info']) . '"';
        }

        $set['dispatch_identification'] = '"' . $this->sanitize($data['dispatch_id']) . '"';
        $set['dispatched_at'] = 'FROM_UNIXTIME("' . strtotime(str_replace('.', '-', $data['dispatched_at'])) . '")';

        // other vehicles
        $set['other_vehicles'] = array();
        if (!empty($data['other_vehicles']) && is_array($data['other_vehicles'])) {
            foreach ($data['other_vehicles'] as $key => $vehicle) {
                if (!empty($vehicle['fullname'])) {
                    $set['other_vehicles'][$key]['fullname'] = '"' . $this->sanitize($vehicle['fullname']) . '"';
                }
                if (!empty($vehicle['unit'])) {
                    $set['other_vehicles'][$key]['unit'] = '"' . $this->sanitize($vehicle['unit']) . '"';
                }
                $set['other_vehicles'][$key]['vehicle_type_id'] = isset($vehicle['vehicle_type_id']) ? '"' . $this->sanitize($vehicle['vehicle_type_id'], 'int') . '"' : 'NULL';
                if (!empty($vehicle['callsign'])) {
                    $set['other_vehicles'][$key]['callsign'] = '"' . $this->sanitize($vehicle['callsign']) . '"';
                }
                if (!empty($vehicle['vehicle'])) {
                    $set['other_vehicles'][$key]['vehicle'] = '"' . $this->sanitize($vehicle['vehicle']) . '"';
                }
            }
        }

        // unit vehicles
        $set['unit_vehicles'] = array();
        if (!empty($data['unit_vehicles']) && is_array($data['unit_vehicles'])) {
            foreach ($data['unit_vehicles'] as $key => $vehicle) {
                if (!empty($vehicle['fullname'])) {
                    $set['unit_vehicles'][$key]['fullname'] = '"' . $this->sanitize($vehicle['fullname']) . '"';
                }
                $set['unit_vehicles'][$key]['unit_vehicle_id'] = isset($vehicle['unit_vehicle_id']) ? '"' . $this->sanitize($vehicle['unit_vehicle_id'], 'int') . '"' : 'NULL';
            }
        }

        return $set;
    }

    # ...................................................................
    /**
     * Saves a parsed and linked dispatch record to the database.
     *
     * @param array|false|null $set The structured data array to be saved to database.
     * @param array|int|null $ids The ID(s) of the dispatch to update, or null to insert a new record.
     * @param string|null $special Special operation mode (e.g., 'IODU' for Insert On Duplicate Update).
     * @return int|false The ID of the newly inserted dispatch, or false on failure.
     */
    public function setOff(array|false|null $set = null, array|int|null $ids = null, string|null $special = null): int|false {
        if (!is_array($set)) {
            return (false);
        }

        $next_id = null;

        # separate other vehicles
        $other_vehicles = $set['other_vehicles'];
        unset($set['other_vehicles']);

        # separate unit vehicles
        $unit_vehicles = $set['unit_vehicles'];
        unset($set['unit_vehicles']);

        # save regular set data
        if (@count($set) >= 1) {
            $next_id = parent::set($set, $ids, $special);
        }

        if (!$ids) {
            $ids = $next_id;
            // $new = true;
        }

        # _______________________________________________________________
        # OTHER VEHICLES
        if (isset($other_vehicles) && is_array($other_vehicles) && is_numeric($ids)) {
            # remove existing
            $this->DB->query('DELETE FROM dispatch_other_vehicle WHERE dispatch_id="' . (int) $ids . '";');

            # save
            if (count($other_vehicles) > 0) {
                $sql = array();
                foreach ($other_vehicles as $key => $value) {
                    $sql[] = '(' .
                        (int) $ids . ', ' .
                        (!empty($value['unit']) ? $value['unit'] : 'null') . ', ' .
                        (!empty($value['vehicle_type_id']) ? $value['vehicle_type_id'] : 'null') . ', ' .
                        (!empty($value['vehicle']) ? $value['vehicle'] : 'null') . ', ' .
                        (!empty($value['callsign']) ? $value['callsign'] : 'null') . ', ' .
                        (!empty($value['fullname']) ? $value['fullname'] : 'null') . ')';
                }

                $this->DB->query('INSERT INTO dispatch_other_vehicle (dispatch_id, unit, vehicle_type_id, vehicle, callsign, fullname) VALUES ' . implode(', ', $sql) . ';');
            }
        }
        # _______________________________________________________________
        # UNIT VEHICLES
        if (isset($unit_vehicles) && is_array($unit_vehicles) && is_numeric($ids)) {
            # remove existing
            $this->DB->query('DELETE FROM dispatch_unit_vehicle WHERE dispatch_id="' . (int) $ids . '";');

            # save
            if (count($unit_vehicles) > 0) {
                $sql = array();
                foreach ($unit_vehicles as $key => $value) {
                    $sql[] = '(' .
                        (int) $ids . ', ' .
                        (!empty($value['unit_vehicle_id']) ? $value['unit_vehicle_id'] : 'null') . ', ' .
                        (!empty($value['fullname']) ? $value['fullname'] : 'null') . ')';
                }

                $this->DB->query('INSERT INTO dispatch_unit_vehicle (dispatch_id, unit_vehicle_id, fullname) VALUES ' . implode(', ', $sql) . ';');
            }
        }
        # _______________________________________________________________

        return ($ids);
    }
}
