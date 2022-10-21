<?php 
define('PSGLACRE_1.0', '1.0');


function plugin_init_psglacre() {
    global $PLUGIN_HOOKS;
 
    //required!
     Plugin::registerClass('PluginPsglacreMaketab', ['addtabon' => [ 'Computer' ]]);
      $PLUGIN_HOOKS['add_javascript']['psglacre'][] = 'js/scripts.js.php';
      $PLUGIN_HOOKS['csrf_compliant']['psglacre'] = true;
      
  
   //   $PLUGIN_HOOKS['config_page']['psglacre'] = 'front/config.form.php';

   




 }

 function plugin_version_psglacre() {
    return [
       'name'           => 'L A C R E - COMPUTADORES',
       'version'        => '1.0',
       'author'         => '<a href="https://github.com/n0cturn0">Luiz Augusto</a>',
       'license'        => 'GLPv3',
       'homepage'       => 'http://www.psgtecnologiaaplicada.com.br',
       'requirements'   => [
          'glpi'   => [
             'min' => '9.1'
          ]
       ]
    ];
 }

 function plugin_psglacre_check_prerequisites() {
    //do what the checks you want
    return true;
 }

 function plugin_psglacre_check_config($verbose = false) {
    if (true) { // Your configuration check
       return true;
    }
 
    if ($verbose) {
       echo "Instalado, Mas não configurado";
    }
    return false;
 }