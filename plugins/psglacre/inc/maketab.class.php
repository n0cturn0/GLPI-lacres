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
    //Verifica se o computador ja possui lacre
    if ($cont == 0) { 
        $msg_header = 'ESTE COMPUTADOR ESTÁ RECEBENDO LACRE PELA PRIMEIRA VEZ';
       } else {
        $msg_header = 'ESTE COMPUTADOR JÁ HAVIA SIDO LACRADO ANTES';
        }
   
    echo $msg_header;
    echo "<form action='".$CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php' method='post'>";
    echo '<table class="tab_cadre_fixehov">
        <tbody>
        <tr class="noHover">
            <th colspan="8">
                    <label>Id do computador:  </label> <input type="text" name="idcomputador" value="'.$computador.'">
                    <label>Número do lacre:  </label> <input type="text" name="lacrenumber" value="" placeholder="digite o número do lacre">
            </th>
        </tr>
        <tr class="noHover">
        <th colspan="8">
      
        <input type="submit" value="Cadastrar Lacre" name="cadastrosemticket" class="submit">
        </th>
    </tr>
        </tbody>
        </table>
        </form>';
   
   }
   
 
   public static function getConfig()
   {
       if (count(self::$config)) {
           return self::$config;
       } 
   }





  
}