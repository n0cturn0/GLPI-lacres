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
        $result = $DB->query("SELECT *, glpi_computers.otherserial FROM glpi_computer_lacre_hystori
                    INNER JOIN glpi_computers ON
                            glpi_computers.id = glpi_computer_lacre_hystori.computer_id where computer_id = $computador");
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


        $color = '#FF0000';
        $lcolor='#00008B';
        foreach ($result as $key => $value) 
        {
        switch ($value['status'] ) {
        case 1:
            if (empty($value['otherserial'])){
                $msg = "Computador <font color='".$color."'>sem número de inventário encontrado.</font> Foi <b><n>lacrado<n></b> pela primeira vez, com o lacre <font color='".$lcolor."'>número: "  . $value['lacre_number'] . "</font> em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username'];
            } else {
                $msg = "Computador com inventário número: ". $value['otherserial']. " Foi LACRADO pela primeira vez, com o lacre <font color='".$lcolor."'>número :"  . $value['lacre_number'] . "</font> em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username'];
            }
       
        break;
        case 2:
            if (empty($value['otherserial'])){
                $msg = "Computador <font color='".$color."'>sem número de inventário encontrado.</font>Teve seu lacre <b>validado</b> mantendo o <font color='".$lcolor."'>número :"  . $value['lacre_number'] . "</font> em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username'];
            }
        $msg = "Computador com o inventário ". $value['otherserial']. " Teve seu lacre validado mantendo o <font color='".$lcolor."'> número :"  . $value['lacre_number'] . "</font> em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username']; 
        break;
        case 3:
            if (empty($value['otherserial'])){
                $msg = "Computador <font color='".$color."'>sem número de invetário encontrador.</font>  Teve seu lacre substituído pelo de <font color='".$lcolor."'> número :"  . $value['lacre_number'] . "</font> em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username']; 
            }
        $msg = "Computador com o inventário ". $value['otherserial']. " Teve seu lacre substituído pelo de <font color='".$lcolor."'> número :"  . $value['lacre_number'] . "</font> em: " . $value['data_alteracao'] .  " pelo usuário " . $value['username']; 
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