<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class NumvalService implements FactoryInterface {

    public $items = array();
    private $template;

    public function __construct($translator){
        $this->translator = $translator;

        //set default numvals
        $numval_options = $this->getDefaultOptions();
        foreach ($numval_options as $key => $options) {
            $numval = new Numval;
            $numval->populate($options);
            $numval->setKey($key);
            $this->addItem($numval, $key);
        }


        //default template
        $this->template = array(
            'general' => array(
                'name' => $this->translator->translate('General', 'casasoft-standards'),
                'items' => array(
                    /*'floor' => array(
                        'required' => false,
                    ),
                    'year_built' => array(
                        'required' => false,
                    ),
                    'year_last_renovated' => array(
                        'required' => false,
                    ),*/
                    'ceiling_height' => array(
                        'required' => false,
                    ),
                    'hall_height' => array(
                        'required' => false,
                    ),
                    'utilization_number' => array(
                        'required' => false,
                    ),
                    'construction_utilization_number' => array(
                        'required' => false,
                    ),
                    'property_land_price' => array(
                        'required' => false,
                    ),
                    
                )
            ),
            'number_of' => array(
                'name' => $this->translator->translate('Number of', 'casasoft-standards'),
                'items' => array(
                    'number_of_floors' => array(
                        'required' => false,
                    ),
                    'number_of_rooms' => array(
                        'required' => true,
                    ),
                    'number_of_bathrooms' => array(
                        'required' => true,
                    ),
                )
            ),
            'areas' => array(
                'name' => $this->translator->translate('Areas', 'casasoft-standards'),
                'items' => array(
                    'area_sia_gf' => array(
                        'required' => false,
                    ),
                    /*'area_sia_ngf' => array(
                        'required' => false,
                    ),*/
                    'area_sia_nf' => array(
                        'required' => false,
                    ),
                    /*'area_bwf' => array(
                        'required' => true,
                    ),*/
                    'area_nwf' => array(
                        'required' => false,
                    ),
                    'area_property_land' => array(
                        'required' => false,
                    ),
                    /*'cubature_gva' => array(
                        'required' => false,
                    ),*/
                    'cubature_sia' => array(
                        'required' => false,
                    ),
                )
            ),
            'distances' => array(
                'name' => $this->translator->translate('Distances', 'casasoft-standards'),
                'items' => array(
                    'distance_kindergarten' => array(
                        'required' => false,
                    ),
                    'distance_primary_school' => array(
                       'required' => false,
                    ),
                    'distance_high_school' => array(
                       'required' => false,
                    ),
                    'distance_college_university' => array(
                       'required' => false,
                    ),
                    'distance_bus_stop' => array(
                        'required' => false,
                    ),
                    'distance_train_station' => array(
                      'required' => false,
                    ),
                    'distance_post' => array(
                       'required' => false,
                    ),
                    'distance_bank' => array(
                       'required' => false,
                    ),
                    'distance_cable_railway_station' => array(
                       'required' => false,
                    ),
                    'distance_boat_dock' => array(
                       'required' => false,
                    ),
                    'distance_public_transport' => array(
                       'required' => false,
                    ),
                    'distance_shop' => array(
                       'required' => false,
                    ),
                    'distance_motorway' => array(
                       'required' => false,
                    ),
                )
            )
        );
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getTemplate(){
        return $this->template;
    }
    public function setTemplate($template){
        $this->template = $template;
    }

    public function getDefaultOptions(){
        return array(
            'floor' => array(
                'label' => $this->translator->translate('Floor', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int_signed',
                'si' => '',
            ),
            'number_of_floors' => array(
                'label' => $this->translator->translate('Floors', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => '',
            ),
            'number_of_apartments' => array(
                'label' => $this->translator->translate('Apartments', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => '',
            ),
            'number_of_bathrooms' => array(
                'label' => $this->translator->translate('Bathrooms', 'casasoft-standards'),
                'icon' => '',
                'type' => 'float_half',
                'si' => '',
            ),
            'number_of_parcels' => array(
                'label' => $this->translator->translate('Numver of parcels', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => '',
            ),
            'distance_kindergarten' => array(
                'label' => $this->translator->translate('Distance to kindergarten', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_primary_school' => array(
                'label' => $this->translator->translate('Distance to primary school', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_high_school' => array(
                'label' => $this->translator->translate('Distance to high school', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_college_university' => array(
                'label' => $this->translator->translate('Distance to college', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_bus_stop' => array(
                'label' => $this->translator->translate('Distance to bus stop', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_train_station' => array(
                'label' => $this->translator->translate('Distance to train station', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_post' => array(
                'label' => $this->translator->translate('Distance to post', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_bank' => array(
                'label' => $this->translator->translate('Distance to bank', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_cable_railway_station' => array(
                'label' => $this->translator->translate('Distance to railway station', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_boat_dock' => array(
                'label' => $this->translator->translate('Distance to boat dock', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_public_transport' => array(
                'label' => $this->translator->translate('Distance to public transport', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_shop' => array(
                'label' => $this->translator->translate('Distance to shop', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'distance_motorway' => array(
                'label' => $this->translator->translate('Distance to freeway', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'number_of_rooms' => array(
                'label' => $this->translator->translate('Rooms', 'casasoft-standards'),
                'icon' => 'glyphicon glyphicon-th-large',
                'type' => 'float_half',
                'si' => '',
            ),
            'max_elevator_weight' => array(
                'label' => $this->translator->translate('Max. elevator weight', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'kg',
            ),

            'area_property_land' => array( //is this not SIA-AGF?!
                'label' => $this->translator->translate('Property land area', 'casasoft-standards'),
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),  




            /* SIA 
            ┌───────────────────────────────────────────────┐
            │                       GF                      │ Geschossfläche
            ├───────────────────────────────┰───────────────┧
            │              NGF              ┃       KF      ┃ Nettogeschossfläche + Konstruktionsfläche
            ├───────────────┬───────┬───────╂───────┬───────┨
            │       NF      │  VF   │  FF   ┃  KFT  │  KFN  ┃ (Nutzfläche + Verkehrsfläche + Funktionsfläche) (FK tragend + KF nicht-tragend)
            ├───────┬───────┼───────┴───────┺━━━━━━━┷━━━━━━━┛
            │  NNF  │  HNF  │ Neben + Hauptnutzfläche
            └───────┴───────┘
            */
            'area_sia_gf' => array( //ngf + kf
                'label' => $this->translator->translate('Total living area', 'casasoft-standards'), //Bruttogeschossfläche oder Geschossfläche
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
                'area_sia_ngf' => array(
                    'label' => $this->translator->translate('Net floor area', 'casasoft-standards'), //Nettogeschossfläche
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm2',
                ),
                    'area_sia_nf' => array( // hnf + nnf
                        'label' => $this->translator->translate('Floorspace', 'casasoft-standards'), //Nutzfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                        'area_sia_hnf' => array(
                            'label' => $this->translator->translate('Main floor space', 'casasoft-standards'), //Hauptnutzfläche
                            'icon' => 'fa fa-retweet',
                            'type' => 'int',
                            'si' => 'm2',
                        ),
                        'area_sia_nnf' => array(
                            'label' => $this->translator->translate('Secondary usable area', 'casasoft-standards'), //Nebennutzfläche
                            'icon' => 'fa fa-retweet',
                            'type' => 'int',
                            'si' => 'm2',
                        ),

                    'area_sia_vf' => array(
                        'label' => $this->translator->translate('Traffic area', 'casasoft-standards'), //Verkehrsfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                    'area_sia_ff' => array(
                        'label' => $this->translator->translate('Functional surface', 'casasoft-standards'), //Funktionsfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),


                'area_sia_kf' => array( //kft + kfn
                    'label' => $this->translator->translate('Construction area', 'casasoft-standards'), //Konstruktionsfläche
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm2',
                ),
                    'area_sia_kft' => array(
                        'label' => $this->translator->translate('Construction area supporting', 'casasoft-standards'), //Konstruktionsfläche tragend
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                    'area_sia_kfn' => array(
                        'label' => $this->translator->translate('Construction area non-supporting', 'casasoft-standards'), //Konstruktionsfläche nicht-tragend
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),

            /* SIA 
            ┌───────────────────────────────────────────────┐
            │                      AGF                      │ Aussen-Geschossfläche
            ├───────────────────────────────┰───────────────┧
            │             ANGF              ┃      AKF      ┃ Aussen-Nettogeschossfläche + Aussen-Konstruktionsfläche
            ├───────────────┬───────┬───────╂───────┬───────┨
            │      ANF      │  AVF  │  AFF  ┃  AKFT │ AKFN  ┃ Aussen-* (Nutzfläche + Verkehrsfläche + Funktionsfläche) (FK tragend + KF nicht-tragend)
            └───────────────┴───────┴───────┺━━━━━━━┷━━━━━━━┛
            */
            'area_sia_agf' => array(
                'label' => $this->translator->translate('SIA-AGF', 'casasoft-standards'), //Bruttogeschossfläche oder Aussen-Geschossfläche
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
                'area_sia_angf' => array(
                    'label' => $this->translator->translate('SIA-ANGF', 'casasoft-standards'), //Aussen-Nettogeschossfläche
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm2',
                ),
                    'area_sia_anf' => array(
                        'label' => $this->translator->translate('SIA-ANF', 'casasoft-standards'), //Aussen-Nutzfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                    'area_sia_avf' => array(
                        'label' => $this->translator->translate('SIA-AVF', 'casasoft-standards'), //Aussen-Verkehrsfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                    'area_sia_aff' => array(
                        'label' => $this->translator->translate('SIA-AFF', 'casasoft-standards'), //Aussen-Funktionsfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),


                'area_sia_akf' => array(
                    'label' => $this->translator->translate('SIA-AFK', 'casasoft-standards'), //Aussen-Konstruktionsfläche
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm2',
                ),
                    'area_sia_akft' => array(
                        'label' => $this->translator->translate('SIA-AFFT', 'casasoft-standards'), //Aussen-Konstruktionsfläche tragend
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                    'area_sia_akfn' => array(
                        'label' => $this->translator->translate('SIA-AFFN', 'casasoft-standards'), //Aussen-Konstruktionsfläche nicht-tragend
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),


            /* SIA 
            ┌───────────────────────────────────────────────┐
            │                      GV                       │ Gebäudevolumen
            ├───────────────────────────────┬───────────────┤
            │             ANGV              │      AKV      │ Nettogebäudevolumen + Konstruktions-Volumen
            ├───────────────┬───────┬───────┼───────────────┘
            │      ANV      │  AVV  │  AFV  │   Nutzvolumen + Verkehrsvolumen + Funktionsvolumen
            └───────────────┴───────┴───────┘
            */
            'area_sia_gv' => array(
                'label' => $this->translator->translate('SIA-GV', 'casasoft-standards'), //Bruttogeschossvolumen oder Aussen-Geschossvolume
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm3',
            ),
                'area_sia_angf' => array(
                    'label' => $this->translator->translate('SIA-ANGV', 'casasoft-standards'), //Aussen-Nettogeschossvolume
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm3',
                ),
                    'area_sia_anf' => array(
                        'label' => $this->translator->translate('SIA-ANV', 'casasoft-standards'), //Aussen-Nutzvolume
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm3',
                    ),
                    'area_sia_avf' => array(
                        'label' => $this->translator->translate('SIA-AVV', 'casasoft-standards'), //Aussen-Verkehrsvolume
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm3',
                    ),
                    'area_sia_aff' => array(
                        'label' => $this->translator->translate('SIA-AFV', 'casasoft-standards'), //Aussen-Funktionsvolume
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm3',
                    ),


                'area_sia_akf' => array(
                    'label' => $this->translator->translate('SIA-AFV', 'casasoft-standards'), //Aussen-Konstruktionsvolume
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm3',
                ),


            /* SIA 
            ┌───────────────────────────────────────────────┐
            │                      GSF                      │ Grundstückfläche
            ├───────────────────────────────┬───────────────┤
            │               UF              │      GGF      │ Umgebungsfläche + Gebäudegrundfläche
            ├───────────────┬───────────────┼───────────────┘
            │      BUF      │      UUF      │ Bearbeitete Umgebungsfläche + Unbearbeitete Umgebungsfläche
            └───────────────┴───────────────┘
            */
            'area_sia_gsf' => array(
                'label' => $this->translator->translate('SIA-GSF', 'casasoft-standards'), //Bruttogeschossfläche oder Aussen-Geschossfläche
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
                'area_sia_uo' => array(
                    'label' => $this->translator->translate('SIA-UO', 'casasoft-standards'), //Aussen-Nettogeschossfläche
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm2',
                ),
                    'area_sia_buf' => array(
                        'label' => $this->translator->translate('SIA-BUF', 'casasoft-standards'), //Aussen-Nutzfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                    'area_sia_uuf' => array(
                        'label' => $this->translator->translate('SIA-UUF', 'casasoft-standards'), //Aussen-Verkehrsfläche
                        'icon' => 'fa fa-retweet',
                        'type' => 'int',
                        'si' => 'm2',
                    ),
                'area_sia_ggf' => array(
                    'label' => $this->translator->translate('SIA-GGF', 'casasoft-standards'), //Aussen-Konstruktionsfläche
                    'icon' => 'fa fa-retweet',
                    'type' => 'int',
                    'si' => 'm2',
                ),


             //ziffern
            'area_sia_gfz' => array( //GF/AGF Geschossflächenziffer
                'label' => $this->translator->translate('SIA-GFZ', 'casasoft-standards'),
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
            'area_sia_az' => array( // Ausnützungsziffer ?
                'label' => $this->translator->translate('SIA-AZ', 'casasoft-standards'),
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
            'area_sia_bmz' => array( // Baumassenziffer ?
                'label' => $this->translator->translate('SIA-BMZ', 'casasoft-standards'), 
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
            'area_sia_uz' => array( // Uberbauungsziffer ?
                'label' => $this->translator->translate('SIA-BMZ', 'casasoft-standards'), 
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
            'area_sia_gz' => array( // Gebäudefläche / Grundstückfläche = Gebäudeflächeziffer ?
                'label' => $this->translator->translate('SIA-BMZ', 'casasoft-standards'), 
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),


            //OLD?
            'cubature_gva' => array(
                'label' => $this->translator->translate('Cubature GVA', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm3',
            ),
            'cubature_sia' => array( //SIA GV (Nettogebäudevolumen ngv)
                'label' => $this->translator->translate('Cubature SIA', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm3',
            ),
            'utilization_number' => array(
                'label' => $this->translator->translate('Utilization number', 'casasoft-standards'),
                'icon' => '',
                'type' => 'float',
                'si' => '%',
            ),
            'construction_utilization_number' => array( //baumassenzahl
                'label' => $this->translator->translate('Construction utilization number', 'casasoft-standards'),
                'icon' => '',
                'type' => 'float',
                'si' => '%',
            ),
            'area_bwf' => array( // ?????
                'label' => $this->translator->translate('Total living area', 'casasoft-standards'), //Bruttowohnfläche
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
            'area_nwf' => array( // GF - KF - FF - NNF   oder    HNF + VF
                'label' => $this->translator->translate('Net living area', 'casasoft-standards'), //Nettowohnfläche
                'icon' => 'fa fa-retweet',
                'type' => 'int',
                'si' => 'm2',
            ),
            //aka nicht wohnfläche (manchmall komischerweise als Nutzfläche deklariert)  = NNF


            


            'year_built' => array(
                'label' => $this->translator->translate('Year built', 'casasoft-standards'),
                'icon' => 'glyphicon-lg glyphicon-lg-settings',
                'type' => 'plain',
                'si' => '',
            ),
            'year_last_renovated' => array(
                'label' => $this->translator->translate('Year of last renovation', 'casasoft-standards'),
                'icon' => '',
                'type' => 'plain',
                'si' => '',
            ),
            'ceiling_height' => array(
                'label' => $this->translator->translate('Ceiling height', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'hall_height' => array(
                'label' => $this->translator->translate('Hall height', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'm',
            ),
            'maximal_floor_loading' => array(
                'label' => $this->translator->translate('Max. floor loading', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'kg',
            ),
            'carrying_capacity_crane' => array(
                'label' => $this->translator->translate('Carrying capacity for crane', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'kg',
            ),
            'carrying_capacity_elevator' => array(
                'label' => $this->translator->translate('Carrying capacity for elevator', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => 'kg',
            ),

            'property_land_price' => array(
                'label' => $this->translator->translate('Land price', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => '',
            ),

            'gross_premium' => array(
                'label' => $this->translator->translate('Gross premium', 'casasoft-standards'),
                'icon' => '',
                'type' => 'int',
                'si' => '',
            ),
        );
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
        if (isset($this->items[$key])) {
            unset($this->items[$key]);
        }
        else {            
            throw new \Exception("Invalid key $key.");
        }
    }

    public function getItem($key) {
        if (isset($this->items[$key])) {
            return $this->items[$key];
        }
        else {
            throw new \Exception("Invalid key $key.");
        }
    }

    public function getItems(){
        return $this->items;
    }

    public function keys() {
        return array_keys($this->items);
    }

    public function length() {
        return count($this->items);
    }

    public function keyExists($key) {
        return isset($this->items[$key]);
    }

    public function isDistanceSeekable($key){
        if (in_array($key, array_keys($this->template['distances']['items']))) {
            return true;
        } else {
            return false;
        }
        

    }

}