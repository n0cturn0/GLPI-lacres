<?php
include ('../../../inc/includes.php');
header('Content-Type: text/javascript');
?>
"use strict";

var modalWindow;
var rootDoc          = CFG_GLPI['root_doc'];
var myPluginsRootDoc = rootDoc + '/' + GLPI_PLUGINS_PATH.myPlugins;

$(function() {
   modalWindow = $("<div></div>").dialog({
      width: 400,
      autoOpen: false,
      height: "300",
      modal: true,
      position: {my: 'center'},
      open: function( event, ui ) {
         //remove existing tinymce when reopen modal (without this, tinymce don't load on 2nd opening of dialog)
         modalWindow.find('.mce-container').remove();
      }
   });
});

var plugin_myPlugins = new function() {
   this.spinner = '<div"><img src="../../../pics/spinner.48.gif" style="margin-left: auto; margin-right: auto; display: block;" width="48px"></div>'

   this.modalSetings = {
      autoOpen: false,
      height: '300px',
      minHeight: '300px',
      width: '400px',
      minWidth: '400px',
      modal: true,
      position: {my: 'center'},
      close: function() {
         $(this).dialog('close');
         $(this).remove();
      }
   }

   this.plugin_myPlugins_scrollToModal = function (modalWindow) {
      $('html, body').animate({
         scrollTop: $(modalWindow).closest('.ui-dialog').offset().top
      }, 300);
   }

   this.showLabel = function (name, inventoryNumber, bgColor) {
      modalWindow.load(
        myPluginsRootDoc + '/ajax/myPlugins.php', {
           name: name,
           inventoryNumber : inventoryNumber,
           bgColor : bgColor
        }
      ).dialog('open');
      // this.plugin_myPlugins_scrollToModal($(modalWindow));
   }

}