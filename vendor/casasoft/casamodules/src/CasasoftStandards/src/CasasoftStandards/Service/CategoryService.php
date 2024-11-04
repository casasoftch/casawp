<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class CategoryService {

    public $items = array();
    public $groups = array();

    public function __construct($translator){
        $this->translator = $translator;
        $this->groups = $this->getDefaultGroupOptions();
    }


    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getDefaultOptions(){
        $array =  array(
            'apartment' => array('label' => $this->translator->translate('Apartment', 'casasoft-standards'),),
            'attic-flat' => array('label' => $this->translator->translate('Attic flat', 'casasoft-standards'),),
            'bachelor-flat' => array('label' => $this->translator->translate('Bachelor flat', 'casasoft-standards'),),
            'bifamiliar-house' => array('label' => $this->translator->translate('Bifamiliar house', 'casasoft-standards'),),
            'building-land' => array('label' => $this->translator->translate('Building land', 'casasoft-standards'),),
            'double-garage' => array('label' => $this->translator->translate('Double garage', 'casasoft-standards'),),
            'duplex' => array('label' => $this->translator->translate('Duplex', 'casasoft-standards'),),
            'factory' => array('label' => $this->translator->translate('Factory', 'casasoft-standards'),),
            'farm' => array('label' => $this->translator->translate('Farm', 'casasoft-standards'),),
            'farm-house' => array('label' => $this->translator->translate('Farm house', 'casasoft-standards'),),
            'furnished-flat' => array('label' => $this->translator->translate('Furnished flat', 'casasoft-standards'),),
            'garage' => array('label' => $this->translator->translate('Garage', 'casasoft-standards'),),
            'house' => array('label' => $this->translator->translate('House', 'casasoft-standards'),),
            'loft' => array('label' => $this->translator->translate('Loft', 'casasoft-standards'),),
            'mountain-farm' => array('label' => $this->translator->translate('Mountain farm', 'casasoft-standards'),),
            'multiple-dwelling' => array('label' => $this->translator->translate('Multiple dwelling', 'casasoft-standards'),),
            'open-slot' => array('label' => $this->translator->translate('Open slot', 'casasoft-standards'),),
            'parking-space' => array('label' => $this->translator->translate('Parking space', 'casasoft-standards'),),
            'parking' => array('label' => $this->translator->translate('Parking space', 'casasoft-standards'),),
            'plot' => array('label' => $this->translator->translate('Plot', 'casasoft-standards'),),
            'roof-flat' => array('label' => $this->translator->translate('Roof flat', 'casasoft-standards'),),
            'row-house' => array('label' => $this->translator->translate('Row house', 'casasoft-standards'),),
            'single-garage' => array('label' => $this->translator->translate('Single garage', 'casasoft-standards'),),
            'single-house' => array('label' => $this->translator->translate('Single house', 'casasoft-standards'),),
            'single-room' => array('label' => $this->translator->translate('Single room', 'casasoft-standards'),),
            'terrace-flat' => array('label' => $this->translator->translate('Terrace flat', 'casasoft-standards'),),
            'terrace-house' => array('label' => $this->translator->translate('Terrace house', 'casasoft-standards'),),
            'underground-slot' => array('label' => $this->translator->translate('Underground slot', 'casasoft-standards'),),
            'villa' => array('label' => $this->translator->translate('Villa', 'casasoft-standards'),),
            'chalet' => array('label' => $this->translator->translate('Chalet', 'casasoft-standards'),),
            'studio' => array('label' => $this->translator->translate('Studio', 'casasoft-standards'),),
            'covered-slot' => array('label' => $this->translator->translate('Covered slot', 'casasoft-standards'),),

            //new
            'building-project' => array('label' => $this->translator->translate('Construction project', 'casasoft-standards'),),

            //new new
            'flat' => array('label' => $this->translator->translate('Flat', 'casasoft-standards'),),
            'ground-floor-flat' => array('label' => $this->translator->translate('Ground floor', 'casasoft-standards'),),
            'office' => array('label' => $this->translator->translate('Office', 'casasoft-standards'),),
            'exhibition-space' => array('label' => $this->translator->translate('Exihition space', 'casasoft-standards'),),
            'retail' => array('label' => $this->translator->translate('Retail', 'casasoft-standards'),),
            'bed-and-breakfast' => array('label' => $this->translator->translate('Bed and breakfast', 'casasoft-standards'),),
            'hotel' => array('label' => $this->translator->translate('Hotel', 'casasoft-standards'),),
            'warehouse' => array('label' => $this->translator->translate('Warehouse', 'casasoft-standards'),),
            'workshop' => array('label' => $this->translator->translate('Workshop', 'casasoft-standards'),),
            'car-park' => array('label' => $this->translator->translate('Car park', 'casasoft-standards'),),
            'bungalow' => array('label' => $this->translator->translate('Bungalow', 'casasoft-standards'),),
            'castle' => array('label' => $this->translator->translate('Castle', 'casasoft-standards'),),
            'fuel-station' => array('label' => $this->translator->translate('Fuel station', 'casasoft-standards'),),
            'cafe-bar' => array('label' => $this->translator->translate('Café / Bar', 'casasoft-standards'),),
            'maisonette' => array('label' => $this->translator->translate('Maisonette', 'casasoft-standards'),),
            'penthouse' => array('label' => $this->translator->translate('Penthouse', 'casasoft-standards'),),
            'hobby-room' => array('label' => $this->translator->translate('Atelier', 'casasoft-standards'),),
            'covered-bike-space' => array('label' => $this->translator->translate('Covered bike space', 'casasoft-standards'),),
            'rustico' => array('label' => $this->translator->translate('Rustico', 'casasoft-standards'),),
            'garden-apartment' => array('label' => $this->translator->translate('Garden Apartment', 'casasoft-standards'),),
            'retail-space' => array('label' => $this->translator->translate('Retail space', 'casasoft-standards'),),

            'boat-dry-dock' => array('label' => $this->translator->translate('Boat dry dock', 'casasoft-standards'),),
            'alottmen-garden' => array('label' => $this->translator->translate('Alottment garden', 'casasoft-standards'),),
            'squash-badminton' => array('label' => $this->translator->translate('Squash / Badminton', 'casasoft-standards'),),
            'indoor-tennis-court' => array('label' => $this->translator->translate('Indoor tennis court', 'casasoft-standards'),),
            'tennis-court' => array('label' => $this->translator->translate('Tennis court', 'casasoft-standards'),),
            'sports-hall' => array('label' => $this->translator->translate('Sports hall', 'casasoft-standards'),),
            'campground' => array('label' => $this->translator->translate('Campground / Tent camping', 'casasoft-standards'),),
            'outdoor-swimming-pool' => array('label' => $this->translator->translate('Outdoor swimming pool', 'casasoft-standards'),),
            'indoor-swimming-pool' => array('label' => $this->translator->translate('Indoor swimming pool', 'casasoft-standards'),),
            'golf-course' => array('label' => $this->translator->translate('Golf course', 'casasoft-standards'),),
            'hospital' => array('label' => $this->translator->translate('Hospital', 'casasoft-standards'),),
            'mini-golf-course' => array('label' => $this->translator->translate('Mini-golf course', 'casasoft-standards'),),
            'nursing-home' => array('label' => $this->translator->translate('Nursing home', 'casasoft-standards'),),
            'riding-hall' => array('label' => $this->translator->translate('Riding hall', 'casasoft-standards'),),
            'sanatorium' => array('label' => $this->translator->translate('Sanatorium', 'casasoft-standards'),),
            'sauna' => array('label' => $this->translator->translate('Sauna', 'casasoft-standards'),),
            'solarium' => array('label' => $this->translator->translate('Solarium', 'casasoft-standards'),),
            'old-age-home' => array('label' => $this->translator->translate('Old-age home', 'casasoft-standards'),),
            'home' => array('label' => $this->translator->translate('Home', 'casasoft-standards'),),
            'display-window' => array('label' => $this->translator->translate('Display window', 'casasoft-standards'),),
            'granny-flat' => array('label' => $this->translator->translate('Granny flat', 'casasoft-standards'),),
            'boat-landing-stage' => array('label' => $this->translator->translate('Boat landing stage', 'casasoft-standards'),),
            'horse-box' => array('label' => $this->translator->translate('Horse box', 'casasoft-standards'),),
            'boat-mooring' => array('label' => $this->translator->translate('Boat mooring', 'casasoft-standards'),),
            'cellar-compartment' => array('label' => $this->translator->translate('Cellar compartment', 'casasoft-standards'),),
            'attic-compartment' => array('label' => $this->translator->translate('Attic compartment', 'casasoft-standards'),),

            'agricultural-land' => array('label' => $this->translator->translate('Agricultural land', 'casasoft-standards'),),
            'arcade' => array('label' => $this->translator->translate('Arcade', 'casasoft-standards'),),
            'bakery' => array('label' => $this->translator->translate('Bakery', 'casasoft-standards'),),
            'butcher' => array('label' => $this->translator->translate('Butcher', 'casasoft-standards'),),
            'car-repair-shop' => array('label' => $this->translator->translate('Car repair shop', 'casasoft-standards'),),
            'carpentry-shop' => array('label' => $this->translator->translate('Carpentry shop', 'casasoft-standards'),),
            'casino' => array('label' => $this->translator->translate('Casino', 'casasoft-standards'),),
            'cheese-factory' => array('label' => $this->translator->translate('Cheese factory', 'casasoft-standards'),),
            'club-disco' => array('label' => $this->translator->translate('Club disco', 'casasoft-standards'),),
            'commercial-lot' => array('label' => $this->translator->translate('Commercial lot', 'casasoft-standards'),),
            'commercial-space' => array('label' => $this->translator->translate('Commercial space', 'casasoft-standards'),),
            'doctors-office' => array('label' => $this->translator->translate('Doctors office', 'casasoft-standards'),),
            'earth-sheltered-dwelling' => array('label' => $this->translator->translate('Earth sheltered dwelling', 'casasoft-standards'),),
            'hairdresser' => array('label' => $this->translator->translate('Hairdresser', 'casasoft-standards'),),
            'industrial-lot' => array('label' => $this->translator->translate('Industrial lot', 'casasoft-standards'),),
            'industrial-object' => array('label' => $this->translator->translate('Industrial object', 'casasoft-standards'),),
            'kiosk' => array('label' => $this->translator->translate('Kiosk', 'casasoft-standards'),),
            'laboratory' => array('label' => $this->translator->translate('Laboratory', 'casasoft-standards'),),
            'library' => array('label' => $this->translator->translate('Library', 'casasoft-standards'),),
            'market-garden' => array('label' => $this->translator->translate('Market garden', 'casasoft-standards'),),
            'motel' => array('label' => $this->translator->translate('Motel', 'casasoft-standards'),),
            'movie-theater' => array('label' => $this->translator->translate('Movie theater', 'casasoft-standards'),),
            'multistorey-car-park' => array('label' => $this->translator->translate('Multistorey car park', 'casasoft-standards'),),
            'orphanage' => array('label' => $this->translator->translate('Orphanage', 'casasoft-standards'),),
            'party-room' => array('label' => $this->translator->translate('Party room', 'casasoft-standards'),),
            'pub' => array('label' => $this->translator->translate('Pub', 'casasoft-standards'),),
            'stoeckli' => array('label' => $this->translator->translate('Stoeckli', 'casasoft-standards'),),
            'storage-room' => array('label' => $this->translator->translate('Storage room', 'casasoft-standards'),),

            //idx legacy support
            'restaurant' => array('label' => $this->translator->translate('Restaurant', 'casasoft-standards'),),
            'shopping-center' => array('label' => $this->translator->translate('Shopping center', 'casasoft-standards'),),
            'commercial' => array('label' => $this->translator->translate('Commercial', 'casasoft-standards'),),
            'commercial-plot' => array('label' => $this->translator->translate('Commercial plot', 'casasoft-standards'),),
            'house-part' => array('label' => $this->translator->translate('Part of a house', 'casasoft-standards'),),
            'residential-commercial-building' => array('label' => $this->translator->translate('Residential / commercial building', 'casasoft-standards'),),

            'engadine-house' => array('label' => $this->translator->translate('Engadine house', 'casasoft-standards'),),
            'patrician-house' => array('label' => $this->translator->translate('Patrician house', 'casasoft-standards'),),

            
            
        );


        // INSERT INTO `tbl_object_set_category` (`ID`, `create_time`, `create_user`, `update_time`, `update_user`, `de`, `en`, `fr`, `it`, `rm`, `ru`, `code_301`, `code_102`, `slug`, `del`)
        // VALUES
        //     (1, 'AGRI'
        //     (2, 'APPT'
        //     (3, 'GASTRO'
        //     (5, 'HOUSE'
        //     (6, 'INDUS'
        //     (7, 'PARK'
        //     (8, 'PROP'
        //     (9, 'SECONDARY
        //     (10,'GARDEN',
        //     (11,'PROJECT';


        // INSERT INTO `tbl_object_set_type` (`de`, `FK_category`, `code_301`, `slug`)
        // VALUES
        //     ('Landwirtschaftsbetrieb', 1, 1, NULL),
        //     ('Alpwirtschaft', 1, 2, 'mountain-farm'),
        //     ('Farm', 1, 3, 'farm'),
        //     ('Mansarde', 2, 11, 'roof-flat'),
        //     ('Wohnung', 2, 1, 'apartment'),
        //     ('Maisonette', 2, 2, 'maisonette'),
        //     ('Attikawohnung', 2, 3, 'attic-flat'),
        //     ('Dachwohnung', 2, 4, 'roof-flat'),
        //     ('Studio', 2, 5, 'bachelor-flat'),
        //     ('Einzelzimmer', 2, 6, 'single-room'),
        //     ('Möbl. Wohnobj.', 2, 7, 'furnished-flat'),
        //     ('Terrassenwohnung', 2, 8, 'terrace-flat'),
        //     ('Einliegerwohnung', 2, 9, 'apartment'),
        //     ('Loft', 2, 10, 'loft'),
        //     ('Hotel', 3, 1, 'hotel'),
        //     ('Restaurant', 3, 2, NULL),
        //     ('Café', 3, 3, 'cafe-bar'),
        //     ('Bar', 3, 4, 'cafe-bar'),
        //     ('Club/Disco', 3, 5, NULL),
        //     ('Casino', 3, 6, NULL),
        //     ('Kino/Theater', 3, 7, NULL),
        //     ('Höhlen- / Erdhaus', 5, 9, 'house'),
        //     ('Einfamilienhaus', 5, 1, 'single-house'),
        //     ('Reihen&shy;familienhaus', 5, 2, 'row-house'),
        //     ('Doppel&shy;einfa&shy;milien&shy;haus', 5, 3, 'bifamiliar-house'),
        //     ('Terrassenhaus', 5, 4, 'terrace-house'),
        //     ('Villa', 5, 5, 'villa'),
        //     ('Bauernhaus', 5, 6, 'farm-house'),
        //     ('Mehr&shy;familien&shy;haus', 5, 7, 'multiple-dwelling'),
        //     ('Schloss', 5, 10, 'castle'),
        //     ('Büro', 6, 1, 'office'),
        //     ('Ladenfläche', 6, 2, NULL),
        //     ('Werbefläche', 6, 3, NULL),
        //     ('Gewerbe', 6, 4, NULL),
        //     ('Lager', 6, 5, NULL),
        //     ('Praxis', 6, 6, NULL),
        //     ('Kiosk', 6, 7, NULL),
        //     ('Gärtnerei', 6, 8, NULL),
        //     ('Tankstelle', 6, 9, 'fuel-station'),
        //     ('Autogarage', 6, 10, 'garage'),
        //     ('Käserei', 6, 11, NULL),
        //     ('Metzgerei', 6, 12, NULL),
        //     ('Bäckerei', 6, 13, NULL),
        //     ('Coiffeursalon', 6, 14, NULL),
        //     ('Shoppingcenter', 6, 15, NULL),
        //     ('Fabrik', 6, 16, 'factory'),
        //     ('Industrieobjekt', 6, 17, NULL),
        //     ('Arcade', 6, 18, NULL),
        //     ('Atelier', 6, 19, 'workshop'),
        //     ('Wohn-/Geschäftshaus', 6, 20, NULL),
        //     ('Boot Hallenplatz', 7, 7, NULL),
        //     ('offener Parkplatz', 7, 1, 'parking-space'),
        //     ('Unterstand', 7, 2, 'covered-car-space'),
        //     ('Einzelgarage', 7, 3, 'single-garage'),
        //     ('Doppelgarage', 7, 4, 'double-garage'),
        //     ('Tiefgarage', 7, 5, 'underground-slot'),
        //     ('Industriebauland', 8, 4, 'building-land'),
        //     ('Bauland', 8, 1, 'building-land'),
        //     ('Agrarland', 8, 2, 'plot'),
        //     ('Gewerbeland', 8, 3, 'plot'),
        //     ('Hobbyraum', 9, 0, 'hobby-room'),
        //     ('Schrebergarten', 10, 0, NULL),
        //     ('Squash / Badminton', 3, 8, NULL),
        //     ('Tennishalle', 3, 9, NULL),
        //     ('Tennisplatz', 3, 10, NULL),
        //     ('Sportanlage', 3, 11, NULL),
        //     ('Camping- / Zeltplatz', 3, 12, NULL),
        //     ('Freibad', 3, 13, NULL),
        //     ('Hallenbad', 3, 14, NULL),
        //     ('Golfplatz', 3, 15, NULL),
        //     ('Motel', 3, 16, 'hotel'),
        //     ('Pub', 3, 17, 'cafe-bar'),
        //     ('Bücherei', 6, 21, NULL),
        //     ('Krankenhaus', 6, 22, NULL),
        //     ('Labor', 6, 23, NULL),
        //     ('Minigolfplatz', 6, 24, NULL),
        //     ('Pflegeheim', 6, 25, NULL),
        //     ('Reithalle', 6, 26, NULL),
        //     ('Sanatorium', 6, 27, NULL),
        //     ('Werkstatt', 6, 28, NULL),
        //     ('Partyraum', 6, 29, NULL),
        //     ('Sauna', 6, 30, NULL),
        //     ('Solarium', 6, 31, NULL),
        //     ('Schreinerei', 6, 32, NULL),
        //     ('Altersheim', 6, 33, NULL),
        //     ('Geschäftshaus', 6, 34, NULL),
        //     ('Heim', 6, 35, NULL),
        //     ('Schaufenster', 6, 36, NULL),
        //     ('Parkhaus', 6, 37, NULL),
        //     ('Parkfläche', 6, 38, 'parking-space'),
        //     ('Stöckli', 5, 11, NULL),
        //     ('Chalet', 5, 12, 'chalet'),
        //     ('Rustico', 5, 13, NULL),
        //     ('Boot Stegplatz', 7, 8, NULL),
        //     ('Moto Hallenplatz', 7, 9, NULL),
        //     ('Moto Aussenplatz', 7, 10, NULL),
        //     ('Stallboxe', 7, 11, NULL),
        //     ('Boot Bojenplatz', 7, 12, NULL),
        //     ('Kellerabteil', 9, 1, NULL),
        //     ('Estrichabteil', 9, 2, NULL),
        //     ('Gartenwohnung', 2, 1, NULL),
        //     ('Etagenwohnung', 2, 1, NULL),
        //     ('MFH Entwicklungsanlageobjekte', 5, 7, NULL),
        //     ('MFH Entwicklung Eigentum', 5, 7, NULL),
        //     ('MFH Bestandesanlageobjekte', 5, 7, NULL),
        //     ('Systemhaus', 5, 7, NULL),
        //     ('Gartenwohnung', 2, 1, 'apartment'),
        //     ('Ferienwohnung', 2, 1, 'apartment'),
        //     ('Ferienhaus', 5, 1, 'single-house'),
        //     ('Renditeobjekt', 5, 7, 'multiple-dwelling'),
        //     ('Projektentwicklung', 8, 1, 'building-land'),
        //     ('Gartenwohnung', 2, 1, 'apartment'),
        //     ('Gartenwohnung', 2, 1, 'apartment'),
        //     ('Gartenwohnung', 2, 1, 'apartment'),
        //     ('Erdgeschoss', 2, 1, 'apartment'),
        //     ('Gartenwohnung', 2, 1, 'apartment'),
        //     ('Etagenwohnung', 2, 1, NULL),
        //     ('Gartenwohnung', 2, 1, 'apartment'),
        //     ('Dachmaisonette Wohnung', 2, 4, 'roof-flat'),
        //     ('Projekt', 11, NULL, 'building-project'),
        //     ('Riegelhaus', 5, 1, 'single-house'),
        //     ('Gartenwohnung', 2, 1, 'apartment'),
        //     ('Wohnung', 2, 1, 'apartment'),
        //     ('Engadinerhaus', 5, 1, 'single-house');


        return $array;
    }

    public function getDefaultGroupOptions(){
        $groups = array(
            'house-group' => array(
                'label' => $this->translator->translate('House', 'casasoft-standards'),
                'category_slugs' => array(
                    'bifamiliar-house',
                    'farm-house',
                    'farm',
                    'house',
                    'single-house',
                    'mountain-farm',
                    'row-house',
                    'villa',
                    'hotel',
                    'chalet',
                    'terrace-house',
                ),
            ),
            'apartment-group' => array(
                'label' => $this->translator->translate('Apartment', 'casasoft-standards'),
                'category_slugs' => array(
                    'penthouse',
                    'apartment',
                    'flat',
                    'granny-flat',
                    'ground-floor-flat',
                    'attic-flat',
                    'bachelor-flat',
                    'loft',
                    'roof-flat',
                    'terrace-flat',
                    'maisonette',
                )
            )
        );

        return $groups;
    }

    public function setTranslator($translator) {
        $this->translator = $translator;
        $this->items = null;
    }


    public function hasSlugInGroup($slug, $groupslug){
        if (array_key_exists($groupslug, $this->groups)) {
            if (in_array($slug, $this->groups[$groupslug]['category_slugs'])) {
                return true;
            }
        }
        return false;
    }

    public function hasASlugInGroup($slugs, $groupslug){
        foreach ($slugs as $slug) {
            if ($this->hasSlugInGroup($slug, $groupslug)) {
                return true;
            }
        }
        return false;
    }

    public function addItem($obj, $key = null) {
        if ($key == null) {
            $this->items[] = $obj;
        }
        else {
            if (isset($this->items[$key])) {
                throw new KeyHasUseException("Key $key already in use.");
            }
            else {
                $this->items[$key] = $obj;
            }
        }
    }

    public function deleteItem($key) {
        if (isset($this->getItems()[$key])) {
            unset($this->getItems()[$key]);
        } else {
            throw new \Exception("Invalid key $key.");
        }
    }

    // public function findItem($slug){
    //     foreach ($this->items as $item) {
    //         if ($item->getKey() == $slug) {
    //             return $item;
    //         }
    //     }
    //     return false;
    // }

    public function getGroup($key) {
        if (isset($this->groups[$key])) {
            return $this->groups[$key];
        } else {
            return false;
        }
    }

    public function getItem($key) {
        if (isset($this->getItems()[$key])) {
            return $this->getItems()[$key];
        } else {
            return false;
        }
    }

    public function getItems(){
        if (! $this->items) {
            //set default categorys
            $category_options = $this->getDefaultOptions();
            foreach ($category_options as $key => $options) {
                $category = new Category;
                $category->populate($options);
                $category->setKey($key);
                $this->addItem($category, $key);
            }
        }
        return $this->items;
    }

    public function keys() {
        return array_keys($this->getItems());
    }

    public function length() {
        return count($this->getItems());
    }

    public function keyExists($key) {
        return isset($this->getItems()[$key]);
    }

    public function testCategoryTranslationsAndCasaXMLcompare(){
        /*
            ==== slug ==== , === in XML (bool) === ==== DE ====, ==== FR ====, etc
            ...
            The Following categories where found in the XML schema that have not been translated:
            1.
            2.
            3.
        */

        //load schema
        $schema = 'https://github.com/CasasoftCH/CasaXML/raw/master/schema/schema_7.xsd';



    }


}
