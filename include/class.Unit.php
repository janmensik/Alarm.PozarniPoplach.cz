<?php

namespace PozarniPoplach;

use Janmensik\Jmlib\Modul;
use Janmensik\Jmlib\Database;

class Unit extends Modul
{
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
    public function __construct(Database &$database)
    {
        parent::__construct($database);
    }

    # ...................................................................
    public function getRegions(): array|null
    {
        $regions = $this->DB->getAllRows($this->DB->query(
            'SELECT id, RZPK, title FROM region ORDER BY title ASC',
            'get_regions'
        ));

        return $regions ?: null;
    }

    # ...................................................................
    public function validate(): array
    {

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
