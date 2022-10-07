<?php

class PluginPsglacreMaketab extends CommonDBTM
{
     static $config = array();
    public static $rightname = 'computers';

    static function getTypeName($ps = 0)
    {
       return 'Lacre do computador';
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch ($item::getType()) {
        case Computer::getType():
        return 'Lacre do computador';
        break;
       }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($item::getType()) {
        case Computer::getType():
        self::displayTab($item);
        break;
       }
        return true;
    }

    
   
    static function displayTab($item)
   {
        global $CFG_GLPI;
        global $DB;

        $computador = $item->getID();
        $result = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = $computador");
        $cont = ($result->num_rows);
   
        echo '<br><br><hr>';
        echo '<h2>Históricos das alterações do lacre deste dispositivo</h2>';
        echo '<table class="tab_cadre_fixehov">';
        if ($cont == 0) { 
         echo '
         <tbody>
         <tr class="noHover">
         <th colspan="8">';
         echo 'Sem Registro de lacres para esse computador';  
        echo '</th>
        </tr>
        
        </tr>
        </tbody>
        </table>';

        }  else {


        
        foreach ($result as $key => $value) 
        {
        switch ($value['status'] ) {
        case 1:
        $msg = "Computador com o ID ". $value['computer_id']. " Foi LACRADO pela primeira vez, com o lacre número :"  . $value['lacre_number'] . " em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username'];
        break;
        case 2:
        $msg = "Computador com o ID ". $value['computer_id']. " Teve seu LACRE VALIDADO mantendo o número :"  . $value['lacre_number'] . " em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username']; 
        break;
        case 3:
        $msg = "Computador com o ID ". $value['computer_id']. " Teve seu LACRE SUBSTITUÍDO pelo de  número :"  . $value['lacre_number'] . " em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username']; 
        default:
        # code...
        break;
        }

        echo '
        <tbody>
        <tr class="noHover">
        <th colspan="8">';
        echo $msg;
        }
        }
        echo  '</th>
        </tr>
        </tr>
        </tbody>
        </table>';
        
        }
   
 
        public static function getConfig()
        {
        if (count(self::$config)) { return self::$config; } 
        }
  
        }