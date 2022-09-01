<?php


if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Item_Ticket Class
 *
 *  Relation between Tickets and Items
**/
class Item_Ticket extends CommonDBRelation{


   // From CommonDBRelation
   static public $itemtype_1          = 'Ticket';
   static public $items_id_1          = 'tickets_id';

   static public $itemtype_2          = 'itemtype';
   static public $items_id_2          = 'items_id';
   static public $checkItem_2_Rights  = self::HAVE_VIEW_RIGHT_ON_ITEM;

   const HISTORY_ADD_DEVICE         = 1;
   const HISTORY_UPDATE_DEVICE      = 2;
   const HISTORY_DELETE_DEVICE      = 3;
   const HISTORY_INSTALL_SOFTWARE   = 4;
   const HISTORY_UNINSTALL_SOFTWARE = 5;
   const HISTORY_DISCONNECT_DEVICE  = 6;
   const HISTORY_CONNECT_DEVICE     = 7;
   const HISTORY_LOCK_DEVICE        = 8;
   const HISTORY_UNLOCK_DEVICE      = 9;

   const HISTORY_LOG_SIMPLE_MESSAGE = 12;
   const HISTORY_DELETE_ITEM        = 13;
   const HISTORY_RESTORE_ITEM       = 14;
   const HISTORY_ADD_RELATION       = 15;
   const HISTORY_DEL_RELATION       = 16;
   const HISTORY_ADD_SUBITEM        = 17;
   const HISTORY_UPDATE_SUBITEM     = 18;
   const HISTORY_DELETE_SUBITEM     = 19;
   const HISTORY_CREATE_ITEM        = 20;
   const HISTORY_UPDATE_RELATION    = 21;
   const HISTORY_LOCK_RELATION      = 22;
   const HISTORY_LOCK_SUBITEM       = 23;
   const HISTORY_UNLOCK_RELATION    = 24;
   const HISTORY_UNLOCK_SUBITEM     = 25;
   const HISTORY_LOCK_ITEM          = 26;
   const HISTORY_UNLOCK_ITEM        = 27;
  
   

   /**
    * @since version 0.84
   **/
   function getForbiddenStandardMassiveAction() {

      $forbidden   = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      return $forbidden;
   }


   /**
    * @since version 0.85.5
    * @see CommonDBRelation::canCreateItem()
   **/
   function canCreateItem() {

      $ticket = new Ticket();
      // Not item linked for closed tickets
      if ($ticket->getFromDB($this->fields['tickets_id'])
          && in_array($ticket->fields['status'], $ticket->getClosedStatusArray())) {
         return false;
      }

      if ($ticket->canUpdateItem()) {
         return true;
      }

      return parent::canCreateItem();
   }


   function post_addItem() {

      $ticket = new Ticket();
      $input  = ['id'            => $this->fields['tickets_id'],
                      'date_mod'      => $_SESSION["glpi_currenttime"],
                      '_donotadddocs' => true];

      if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
         $input['_forcenotif'] = true;
      }
      if (isset($this->input['_disablenotif']) && $this->input['_disablenotif']) {
         $input['_disablenotif'] = true;
      }

      $ticket->update($input);
      parent::post_addItem();
   }


   function post_purgeItem() {

      $ticket = new Ticket();
      $input = ['id'            => $this->fields['tickets_id'],
                     'date_mod'      => $_SESSION["glpi_currenttime"],
                     '_donotadddocs' => true];

      if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
         $input['_forcenotif'] = true;
      }
      $ticket->update($input);

      parent::post_purgeItem();
   }


   /**
    * @see CommonDBTM::prepareInputForAdd()
   **/
   function prepareInputForAdd($input) {

      // Avoid duplicate entry
      if (countElementsInTable($this->getTable(), ['tickets_id' => $input['tickets_id'],
                                                   'itemtype'   => $input['itemtype'],
                                                   'items_id'   => $input['items_id']]) > 0) {
         return false;
      }

      $ticket = new Ticket();
      $ticket->getFromDB($input['tickets_id']);

      // Get item location if location is not already set in ticket
      if (empty($ticket->fields['locations_id'])) {
         if (($input["items_id"] > 0) && !empty($input["itemtype"])) {
            if ($item = getItemForItemtype($input["itemtype"])) {
               if ($item->getFromDB($input["items_id"])) {
                  if ($item->isField('locations_id')) {
                     $ticket->fields['items_locations'] = $item->fields['locations_id'];

                     // Process Business Rules
                     $rules = new RuleTicketCollection($ticket->fields['entities_id']);

                     $ticket->fields = $rules->processAllRules(Toolbox::stripslashes_deep($ticket->fields),
                                                Toolbox::stripslashes_deep($ticket->fields),
                                                ['recursive' => true]);

                     unset($ticket->fields['items_locations']);
                     $ticket->updateInDB(['locations_id']);
                  }
               }
            }
         }
      }

      return parent::prepareInputForAdd($input);
   }

   /**
    * @param $item   CommonDBTM object
   **/
   static function countForItem(CommonDBTM $item) {

      $restrict = "`glpi_items_tickets`.`tickets_id` = `glpi_tickets`.`id`
                   AND `glpi_items_tickets`.`items_id` = '".$item->getField('id')."'
                   AND `glpi_items_tickets`.`itemtype` = '".$item->getType()."'".
                   getEntitiesRestrictRequest(" AND ", "glpi_tickets", '', '', true);

      $nb = countElementsInTable(['glpi_items_tickets', 'glpi_tickets'], $restrict);

      return $nb;
   }

   /**
    * Print the HTML ajax associated item add
    *
    * @param $ticket Ticket object
    * @param $options   array of possible options:
    *    - id                  : ID of the ticket
    *    - _users_id_requester : ID of the requester user
    *    - items_id            : array of elements (itemtype => array(id1, id2, id3, ...))
    *
    * @return Nothing (display)
   **/
   static function itemAddForm(Ticket $ticket, $options = []) {
      global $CFG_GLPI;

      $params = ['id'                  => (isset($ticket->fields['id'])
                                                && $ticket->fields['id'] != '')
                                                   ? $ticket->fields['id']
                                                   : 0,
                      '_users_id_requester' => 0,
                      'items_id'            => [],
                      'itemtype'            => '',
                      '_canupdate'          => false];

      $opt = [];

      foreach ($options as $key => $val) {
         if (!empty($val)) {
            $params[$key] = $val;
         }
      }

      if (!$ticket->can($params['id'], READ)) {
         return false;
      }

      $canedit = ($ticket->can($params['id'], UPDATE)
                  && $params['_canupdate']);

      // Ticket update case
      if ($params['id'] > 0) {
         // Get requester
         $class        = new $ticket->userlinkclass();
         $tickets_user = $class->getActors($params['id']);
         if (isset($tickets_user[CommonITILActor::REQUESTER])
             && (count($tickets_user[CommonITILActor::REQUESTER]) == 1)) {
            foreach ($tickets_user[CommonITILActor::REQUESTER] as $user_id_single) {
               $params['_users_id_requester'] = $user_id_single['users_id'];
            }
         }

         // Get associated elements for ticket
         $used = self::getUsedItems($params['id']);
         $usedcount = 0;
         foreach ($used as $itemtype => $items) {
            foreach ($items as $items_id) {
               if (!isset($params['items_id'][$itemtype])
                   || !in_array($items_id, $params['items_id'][$itemtype])) {
                  $params['items_id'][$itemtype][] = $items_id;
               }
               ++$usedcount;
            }
         }
      }

      // Get ticket template
      $tt = new TicketTemplate();
      if (isset($options['_tickettemplate'])) {
         $tt                  = $options['_tickettemplate'];
         if (isset($tt->fields['id'])) {
            $opt['templates_id'] = $tt->fields['id'];
         }
      } else if (isset($options['templates_id'])) {
         $tt->getFromDBWithDatas($options['templates_id']);
         if (isset($tt->fields['id'])) {
            $opt['templates_id'] = $tt->fields['id'];
         }
      }

      $rand  = mt_rand();
      $count = 0;

      echo "<div id='itemAddForm$rand'>";

      // Show associated item dropdowns
      if ($canedit) {
         echo "<div style='float:left'>";
         $p = ['used'       => $params['items_id'],
                    'rand'       => $rand,
                    'tickets_id' => $params['id']];
         // My items
         if ($params['_users_id_requester'] > 0) {
            Item_Ticket::dropdownMyDevices($params['_users_id_requester'], $ticket->fields["entities_id"], $params['itemtype'], 0, $p);
         }
         // Global search
         Item_Ticket::dropdownAllDevices("itemtype", $params['itemtype'], 0, 1, $params['_users_id_requester'], $ticket->fields["entities_id"], $p);
         echo "<span id='item_ticket_selection_information'></span>";
         echo "</div>";

         // Add button
         echo "<a href='javascript:itemAction$rand(\"add\");' class='vsubmit' style='margin-top: 10px;'>"._sx('button', 'Add')."</a><br><br>";
      }

      // Display list
      echo "<div style='clear:both;'>";
      
      if (!empty($params['items_id'])) {
         // No delete if mandatory and only one item
         $delete = $ticket->canAddItem(__CLASS__);
         $cpt = 0;
         foreach ($params['items_id'] as $itemtype => $items) {
            $cpt += count($items);
         }

         if ($cpt == 1 && isset($tt->mandatory['items_id'])) {
            $delete = false;
         }
         foreach ($params['items_id'] as $itemtype => $items) {
            foreach ($items as $items_id) {
               $count++;
               echo self::showItemToAdd(
                  $params['id'],
                  $itemtype,
                  $items_id,
                  [
                     'rand'      => $rand,
                     'delete'    => $delete,
                     'visible'   => ($count <= 5)
                  ]
               );
            }
         }
         // CUSTOMIZAÇÃO CONEXÃO REMOTA
         if($count > 0){
            global $DB, $CFG_GLPI;
            $ticket = $ticket->fields['id'];
            echo "<br><a href='remote: ' class='vsubmit' style='margin-top: 10px;'>"._sx('button', 'Acesso remoto')."</a>";
            if (empty($ticket)){

            } else {
               $result = $DB->query("SELECT * FROM glpi_computers_lacre WHERE  id_ticket='$ticket'");
            $cont = ($result->num_rows);
           // Rotina de Validação dos lacres para computador
            if ($cont == 0 ) {
                echo "<br><a href='".$CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php?ticket_id=".$ticket."' class='vsubmit' style='margin-top: 10px;'>"._sx('button', 'L A C R E')."</a>";
                echo '<br>';
                echo "<p id='label_lacre'>Lacre(s) não validados</p><input id='validar_lacre' type='checkbox' required style='display:none'>";
                  echo "<script type='text/javascript'>";
                  echo "$(document).ready(function() { $('input[type=submit]').click(function(){";
                  echo "if (!$('#validar_lacre').is(':checked')) {alert('Lacre nao validado');}";
                  echo "});});</script>";
            } else {
               echo "<br>";
               echo "<input type='checkbox' checked required>Lacre(s) validados";
            }
            }
            
         }
      }

      

      if ($params['id'] > 0 && $usedcount != $count) {
         $count_notsaved = $count - $usedcount;
         echo "<i>" . sprintf(_n('%1$s item not saved', '%1$s items not saved', $count_notsaved), $count_notsaved)  . "</i>";
      }
      if ($params['id'] > 0 && $usedcount > 5) {
         echo "<i><a href='".$ticket->getFormURL()."?id=".$params['id']."&amp;forcetab=Item_Ticket$1'>"
                  .__('Display all items')." (".$usedcount.")</a></i>";
      }
      echo "</div>";

      foreach (['id', '_users_id_requester', 'items_id', 'itemtype', '_canupdate'] as $key) {
         $opt[$key] = $params[$key];
      }

      $js  = " function itemAction$rand(action, itemtype, items_id) {";
      $js .= "    $.ajax({
                     url: '".$CFG_GLPI['root_doc']."/ajax/itemTicket.php',
                     dataType: 'html',
                     data: {'action'     : action,
                            'rand'       : $rand,
                            'params'     : ".json_encode($opt).",
                            'my_items'   : $('#dropdown_my_items$rand').val(),
                            'itemtype'   : (itemtype === undefined) ? $('#dropdown_itemtype$rand').val() : itemtype,
                            'items_id'   : (items_id === undefined) ? $('#dropdown_add_items_id$rand').val() : items_id},
                     success: function(response) {";
      $js .= "          $(\"#itemAddForm$rand\").html(response);";
      $js .= "       }";
      $js .= "    });";
      $js .= " }";
      echo Html::scriptBlock($js);
      echo "</div>";
   }


   static function showItemToAdd($tickets_id, $itemtype, $items_id, $options) {
      global $CFG_GLPI;

      $params = [
         'rand'      => mt_rand(),
         'delete'    => true,
         'visible'   => true
      ];

      foreach ($options as $key => $val) {
         $params[$key] = $val;
      }

      $result = "";

      if ($item = getItemForItemtype($itemtype)) {
         if ($params['visible']) {
            $item->getFromDB($items_id);
            $result =  "<div id='{$itemtype}_$items_id'>";
            $result .= $item->getTypeName(1)." : ".$item->getLink(['comments' => true]);
            $result .= Html::hidden("items_id[$itemtype][$items_id]", ['value' => $items_id]);
            if ($params['delete']) {
               $result .= " <span class='fa fa-times-circle pointer' onclick=\"itemAction".$params['rand']."('delete', '$itemtype', '$items_id');\"></span>";
            }
            $result .= "</div>";
         } else {
            $result .= Html::hidden("items_id[$itemtype][$items_id]", ['value' => $items_id]);
         }
      }

      return $result;
   }

   /**
    * Print the HTML array for Items linked to a ticket
    *
    * @param $ticket Ticket object
    *
    * @return Nothing (display)
   **/
   static function showForTicket(Ticket $ticket) {
      global $DB, $CFG_GLPI;

      $instID = $ticket->fields['id'];

      if (!$ticket->can($instID, READ)) {
         return false;
      }

      $canedit = $ticket->canAddItem($instID);
      $rand    = mt_rand();

      $query = "SELECT DISTINCT `itemtype`
                FROM `glpi_items_tickets`
                WHERE `glpi_items_tickets`.`tickets_id` = '$instID'
                ORDER BY `itemtype`";

      $result = $DB->query($query);
      $number = $DB->numrows($result);

      if ($canedit) {
         echo "<div class='firstbloc'>";
         echo "<form name='ticketitem_form$rand' id='ticketitem_form$rand' method='post'
                action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_2'><th colspan='2'>".__('Add an item')."</th></tr>";

         echo "<tr class='tab_bg_1'><td>";
         // Select hardware on creation or if have update right
         $class        = new $ticket->userlinkclass();
         $tickets_user = $class->getActors($instID);
         $dev_user_id = 0;
         if (isset($tickets_user[CommonITILActor::REQUESTER])
                 && (count($tickets_user[CommonITILActor::REQUESTER]) == 1)) {
            foreach ($tickets_user[CommonITILActor::REQUESTER] as $user_id_single) {
               $dev_user_id = $user_id_single['users_id'];
            }
         }

         if ($dev_user_id > 0) {
            self::dropdownMyDevices($dev_user_id, $ticket->fields["entities_id"], null, 0, ['tickets_id' => $instID]);
         }

         $data =  array_keys(getAllDatasFromTable('glpi_items_tickets'));
         $used = [];
         if (!empty($data)) {
            foreach ($data as $val) {
               $used[$val['itemtype']] = $val['id'];
            }
         }

         self::dropdownAllDevices("itemtype", null, 0, 1, $dev_user_id, $ticket->fields["entities_id"], ['tickets_id' => $instID]);
         echo "<span id='item_ticket_selection_information'></span>";
         echo "</td><td class='center' width='30%'>";
         echo "<input type='submit' name='add' value=\""._sx('button', 'Add')."\" class='submit'>";
         echo "<input type='hidden' name='tickets_id' value='$instID'>";
         echo "</td></tr>";
         echo "</table>";
         Html::closeForm();
         echo "</div>";
      }

      echo "<div class='spaced'>";
      if ($canedit && $number) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = ['container' => 'mass'.__CLASS__.$rand];
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixehov'>";
      $header_begin  = "<tr>";
      $header_top    = '';
      $header_bottom = '';
      $header_end    = '';
      if ($canedit && $number) {
         $header_top    .= "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
         $header_top    .= "</th>";
         $header_bottom .= "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
         $header_bottom .= "</th>";
      }
      $header_end .= "<th>".__('Type')."</th>";
      $header_end .= "<th>".__('Entity')."</th>";
      $header_end .= "<th>".__('Name')."</th>";
      $header_end .= "<th>".__('Serial number')."</th>";
      $header_end .= "<th>".__('Inventory number')."</th>";
      if ($canedit && $number) {
         $header_end .= "<th width='10'>".__('Update the item')."</th>";
      }
      echo "<tr>";
      echo $header_begin.$header_top.$header_end;

      $totalnb = 0;
      for ($i=0; $i<$number; $i++) {
         $itemtype = $DB->result($result, $i, "itemtype");
         if (!($item = getItemForItemtype($itemtype))) {
            continue;
         }

         if (in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
            $itemtable = getTableForItemType($itemtype);
            $query = "SELECT `$itemtable`.*,
                             `glpi_items_tickets`.`id` AS IDD,
                             `glpi_entities`.`id` AS entity
                      FROM `glpi_items_tickets`,
                           `$itemtable`";

            if ($itemtype != 'Entity') {
               $query .= " LEFT JOIN `glpi_entities`
                                 ON (`$itemtable`.`entities_id`=`glpi_entities`.`id`) ";
            }

            $query .= " WHERE `$itemtable`.`id` = `glpi_items_tickets`.`items_id`
                              AND `glpi_items_tickets`.`itemtype` = '$itemtype'
                              AND `glpi_items_tickets`.`tickets_id` = '$instID'";

            if ($item->maybeTemplate()) {
               $query .= " AND `$itemtable`.`is_template` = '0'";
            }

            $query .= getEntitiesRestrictRequest(" AND", $itemtable, '', '',
                                                 $item->maybeRecursive())."
                      ORDER BY `glpi_entities`.`completename`, `$itemtable`.`name`";

            $result_linked = $DB->query($query);
            $nb            = $DB->numrows($result_linked);

            for ($prem=true; $data=$DB->fetch_assoc($result_linked); $prem=false) {
               $name = $data["name"];
               if ($_SESSION["glpiis_ids_visible"]
                   || empty($data["name"])) {
                  $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
               }
               if ($_SESSION['glpiactiveprofile']['interface'] != 'helpdesk') {
                  $link     = $itemtype::getFormURLWithID($data['id']);
                  $namelink = "<a href=\"".$link."\">".$name."</a>";
               } else {
                  $namelink = $name;
               }

               echo "<tr class='tab_bg_1'>";
               if ($canedit) {
                  echo "<td width='10'>";
                  Html::showMassiveActionCheckBox(__CLASS__, $data["IDD"]);
                  echo "</td>";
               }
               if ($prem) {
                  $typename = $item->getTypeName($nb);
                  echo "<td class='center top' rowspan='$nb'>".
                         (($nb > 1) ? sprintf(__('%1$s: %2$s'), $typename, $nb) : $typename)."</td>";
               }
               echo "<td class='center'>";
               echo Dropdown::getDropdownName("glpi_entities", $data['entity'])."</td>";
               echo "<td class='center".
                        (isset($data['is_deleted']) && $data['is_deleted'] ? " tab_bg_2_2'" : "'");
               echo ">".$namelink."</td>";
               echo "<td class='center'>".(isset($data["serial"])? "".$data["serial"]."" :"-").
                    "</td>";
               echo "<td class='center'>".
                      (isset($data["otherserial"])? "".$data["otherserial"]."" :"-")."</td>";
               if ($canedit) {
                  echo "<td width='10'>";
                  Html::showMassiveActionCheckBox($itemtype, $data["id"]);
                  echo "</td>";
               }

               echo "</tr>";
            }
            $totalnb += $nb;
         }
      }

      if ($number) {
         echo $header_begin.$header_bottom.$header_end;
      }

      echo "</table>";
      if ($canedit && $number) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();
      }
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Historical') . "</th></tr>";
      echo "</div>";
      // Output events
      echo "<div class='center'><table class='tab_cadre_fixehov'>";

      $header = "<tr><th>" . __('ID') . "</th>";
      $header .= "<th>" . __('Date') . "</th>";
      $header .= "<th>" . __('User') . "</th>";
      $header .= "<th>" . __('Field') . "</th>";
      //TRANS: a noun, modification, change
      $header .= "<th>" . _x('name', 'Update') . "</th>";
      $header .= "<th>" . __('Custo') . "</th>";
      $header .= "</tr>";

      echo $header;
      $total_costs = 0;
      foreach (self::getHistoryData("Computer") as $data) {
         $query = ['FROM' => 'glpi_logs', 'WHERE' => ['id' => $data['id']]];
         $result = $DB->request($query);
         $result = $result->next($result);
         $itemType = $result['itemtype_link'];
         $dateMod = new DateTime($data['date_mod']. '+59 second');
         $dateMod = $dateMod->format('Y-m-d H:i:s');
         
         preg_match_all("/\(([0-9]+)\)/", $data['change'], $match);
         $id_component = $match[sizeof($match) - 1][sizeof($match[0]) - 1];
         
         $query_cost = "SELECT cost_item FROM glpi_plugins_costs_devices_logs WHERE item_type = '$itemType'
                        AND device_id = $id_component
                        AND date_creation <= '$dateMod'
                        ORDER BY date_creation DESC LIMIT 1";
         $final_cost = $DB->request($query_cost);
         $final_cost = $final_cost->next($final_cost);
         $final_cost = $final_cost["cost_item"];
         
         if ($data['display_history']) {
            // show line
            echo "<tr class='tab_bg_2'>";
            echo "<td>" . $data['id'] . "</td>" .
               "<td class='tab_date'>" . $data['date_mod'] . "</td>" .
               "<td>" . $data['user_name'] . "</td>" .
               "<td>" . $data['field'] . "</td>";   
               echo "<td>" . $data['change'] . "</td>";
                  if (explode(' ', $data['change'])[0] == 'Adicionar') {
                     printf("<td> R$ %.2f </td>", $final_cost);
                     $total_costs+= $final_cost;
                  } else {
                     echo "<td> -- </td>";
                  }
            if (!$data == self::getHistoryData("Computer")[sizeof(self::getHistoryData("Computer")) -1]){
               echo "</tr>";
               echo "<td>  </td>";    
            }
         }
         
      }
      
      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp</td>";
      echo "<td>&nbsp</td>";
      echo "<td>&nbsp</td>";
      echo "<td>&nbsp</td>";
      echo "<td style='float:right;'><strong>Custo total</strong> </td>";
      printf( "<td>R$ %.02f</td>", $total_costs);
      echo "</tr>";
      echo $header;      
      echo "</table></div>";
      //printf( "<strong>Total de Custo</strong> R$ %.02f", $total_costs);
   }

   static function getCost($item_type, $device_id)
   {
      global $DB;
      $query = ['FROM' => 'glpi_plugins_costs_devices', 'WHERE' => ['item_type' => $item_type, 'device_id' => $device_id]];
      $result = $DB->request($query);
      $result = $result->next($result);

      return $result['cost_item'];
   }

   static function insertCost($item_type, $device_id, $cost_item)
   {
      global $DB;
      $table_costs_devices  = 'glpi_plugins_costs_devices_logs';

      if ($DB->tableExists($table_costs_devices)) {
         $insert_costs_device = "INSERT INTO  $table_costs_devices (`item_type`, `device_id`, `cost_item`,`ticket_id` `date_creation`)
                                    VALUES ('$item_type', $device_id, $cost_item, $ticket_id, NOW())";
         return $DB->query($insert_costs_device);
      }
      return false;
   }

   function getHistoryData($item){

      global $DB;
      
      $query = "SELECT glpi_logs.*
            FROM psgitsm.glpi_logs
            JOIN glpi_tickets
            ON glpi_tickets.date < glpi_logs.date_mod";
      
      $query_ticket = "SELECT *
                  FROM glpi_tickets
                  WHERE id = ".$_GET['id'];

      $result_ticket = $DB->query($query_ticket);
      $ticket = $DB->fetch_assoc($result_ticket);

      $ticket['solvedate'] != NULL? $query .= " AND glpi_tickets.solvedate > glpi_logs.date_mod " : $query .= " ";

      $query .= "AND glpi_tickets.id = ".$_GET['id']."
            JOIN glpi_items_tickets
            ON glpi_logs.items_id = glpi_items_tickets.items_id AND glpi_items_tickets.tickets_id = ".$_GET['id']."
            WHERE glpi_logs.itemtype = \"$item\"
            AND glpi_logs.itemtype_link LIKE 'Device%'
            ORDER BY glpi_logs.date_mod DESC";
            
            $result = $DB->query($query);
            $changes = [];

            while ($data = $DB->fetch_assoc($result)) {
               $tmp = [];
               $tmp['display_history'] = true;
               $tmp['id']              = $data["id"];
               $tmp['date_mod']        = Html::convDateTime($data["date_mod"]);
               $tmp['user_name']       = $data["user_name"];
               $tmp['field']           = "";
               $tmp['change']          = "";
               $tmp['datatype']        = "";
      
               // This is an internal device ?
               if ($data["linked_action"]) {
                  // Yes it is an internal device
                  switch ($data["linked_action"]) {
                     case self::HISTORY_CREATE_ITEM :
                        $tmp['change'] = __('Add the item');
                        break;
      
                     case self::HISTORY_DELETE_ITEM :
                        $tmp['change'] = __('Delete the item');
                        break;
      
                     case self::HISTORY_LOCK_ITEM :
                        $tmp['change'] = __('Lock the item');
                        break;
      
                     case self::HISTORY_UNLOCK_ITEM :
                        $tmp['change'] = __('Unlock the item');
                        break;
      
                     case self::HISTORY_RESTORE_ITEM :
                        $tmp['change'] = __('Restore the item');
                        break;
      
                     case self::HISTORY_ADD_DEVICE :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        //TRANS: %s is the component name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Add the component'),
                                                 $data["new_value"]);
                        break;
      
                     case self::HISTORY_UPDATE_DEVICE :
                        $tmp['field'] = NOT_AVAILABLE;
                        $change = '';
                        $linktype_field = explode('#', $data["itemtype_link"]);
                        $linktype       = $linktype_field[0];
                        $field          = $linktype_field[1];
                        $devicetype     = $linktype::getDeviceType();
                        $tmp['field']   = $devicetype;
                        $specif_fields  = $linktype::getSpecificities();
                        if (isset($specif_fields[$field]['short name'])) {
                           $tmp['field']   = $devicetype;
                           $tmp['field']  .= " (".$specif_fields[$field]['short name'].")";
                        }
                        //TRANS: %1$s is the old_value, %2$s is the new_value
                        $tmp['change']  = sprintf(__('Change the component %1$s: %2$s'),
                                                  $tmp['field'],
                                                  sprintf(__('%1$s by %2$s'), $data["old_value"],
                                                          $data[ "new_value"]));
                        break;
      
                     case self::HISTORY_DELETE_DEVICE :
                        $tmp['field']=NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        //TRANS: %s is the component name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Delete the component'),
                                                 $data["old_value"]);
                        break;
      
                     case self::HISTORY_LOCK_DEVICE :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        //TRANS: %s is the component name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Lock the component'),
                                                 $data["old_value"]);
                        break;
      
                     case self::HISTORY_UNLOCK_DEVICE :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        //TRANS: %s is the component name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Unlock the component'),
                                                 $data["new_value"]);
                        break;
      
                     case self::HISTORY_INSTALL_SOFTWARE :
                        $tmp['field']  = _n('Software', 'Software', 1);
                        //TRANS: %s is the software name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Install the software'),
                                                 $data["new_value"]);
                        break;
      
                     case self::HISTORY_UNINSTALL_SOFTWARE :
                        $tmp['field']  = _n('Software', 'Software', 1);
                        //TRANS: %s is the software name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Uninstall the software'),
                                                 $data["old_value"]);
                        break;
      
                     case self::HISTORY_DISCONNECT_DEVICE :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        //TRANS: %s is the item name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Disconnect the item'),
                                                 $data["old_value"]);
                        break;
      
                     case self::HISTORY_CONNECT_DEVICE :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        //TRANS: %s is the item name
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Connect the item'),
                                                 $data["new_value"]);
                        break;
      
                     case self::HISTORY_LOG_SIMPLE_MESSAGE :
                        $tmp['field']  = "";
                        $tmp['change'] = $data["new_value"];
                        break;
      
                     case self::HISTORY_ADD_RELATION :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Add a link with an item'),
                                                 $data["new_value"]);
      
                        if ($data['itemtype'] == 'Ticket') {
                           switch ($data['itemtype_link']) {
                              case 'Group':
                                 $itemtick = new Group_Ticket();
                                 break;
      
                              case 'User':
                                 $itemtick = new Ticket_User();
                                 break;
      
                              case 'Supplier':
                                 $itemtick = new Supplier_Ticket();
                                 break;
      
                              default:
                                 $itemtick = false;
                                 break;
                           }
      
                           if ($itemtick !== false) {
                              $table   = $itemtick->getTable();
                              $key     = getForeignKeyFieldForItemType($data['itemtype']);
                              $itemkey = getForeignKeyFieldForItemType($data['itemtype_link']);
                              $iditem  = trim(substr($data['new_value'], strrpos($data['new_value'], '(')+1,
                                             strrpos($data['new_value'], ')')), ')');
      
                              foreach ($DB->request($table, [$key => $data['items_id'],
                                                               $itemkey => $iditem]) as $datalink) {
                                 if ($datalink['type'] == CommonITILActor::REQUESTER) {
                                    $as = __('Requester');
                                 } else if ($datalink['type'] == CommonITILActor::ASSIGN) {
                                    $as = __('Assigned to');
                                 } else if ($datalink['type'] == CommonITILActor::OBSERVER) {
                                    $as = __('Watcher');
                                 }
                                 $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Add a link with an item'),
                                                         sprintf(__('%1$s (%2$s)'), $data["new_value"],
                                                                  $as));
                              }
                           }
                        }
                        break;
      
                     case self::HISTORY_UPDATE_RELATION :
                        $tmp['field']   = NOT_AVAILABLE;
                        if ($linktype_field = explode('#', $data["itemtype_link"])) {
                           $linktype     = $linktype_field[0];
                           $tmp['field'] = $linktype::getTypeName();
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Update a link with an item'),
                                             sprintf(__('%1$s (%2$s)'), $data["old_value"],
                                                $data["new_value"]));
                        break;
      
                     case self::HISTORY_DEL_RELATION :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Delete a link with an item'),
                                                 $data["old_value"]);
                        break;
      
                     case self::HISTORY_LOCK_RELATION :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Lock a link with an item'),
                                                 $data["old_value"]);
                        break;
      
                     case self::HISTORY_UNLOCK_RELATION :
                        $tmp['field'] = NOT_AVAILABLE;
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Unlock a link with an item'),
                                                 $data["new_value"]);
                        break;
      
                     case self::HISTORY_ADD_SUBITEM :
                        $tmp['field'] = '';
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Add an item'),
                                                 sprintf(__('%1$s (%2$s)'), $tmp['field'],
                                                         $data["new_value"]));
      
                        break;
      
                     case self::HISTORY_UPDATE_SUBITEM :
                        $tmp['field'] = '';
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Update an item'),
                                                 sprintf(__('%1$s (%2$s)'), $tmp['field'],
                                                         $data["new_value"]));
                        break;
      
                     case self::HISTORY_DELETE_SUBITEM :
                        $tmp['field'] = '';
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Delete an item'),
                                                 sprintf(__('%1$s (%2$s)'), $tmp['field'],
                                                         $data["old_value"]));
                        break;
      
                     case self::HISTORY_LOCK_SUBITEM :
                        $tmp['field'] = '';
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Lock an item'),
                                                 sprintf(__('%1$s (%2$s)'), $tmp['field'],
                                                         $data["old_value"]));
                        break;
      
                     case self::HISTORY_UNLOCK_SUBITEM :
                        $tmp['field'] = '';
                        if ($item2 = getItemForItemtype($data["itemtype_link"])) {
                           $tmp['field'] = $item2->getTypeName(1);
                        }
                        $tmp['change'] = sprintf(__('%1$s: %2$s'), __('Unlock an item'),
                                                 sprintf(__('%1$s (%2$s)'), $tmp['field'],
                                                         $data["new_value"]));
                        break;
      
                     default :
                        $fct = [$data['itemtype_link'], 'getHistoryEntry'];
                        if (($data['linked_action'] >= self::HISTORY_PLUGIN)
                            && $data['itemtype_link']
                            && is_callable($fct)) {
                           $tmp['field']  = $data['itemtype_link']::getTypeName(1);
                           $tmp['change'] = call_user_func($fct, $data);
                        }
                        $tmp['display_history'] = !empty($tmp['change']);
                  }
      
               } else {
                  $fieldname = "";
                  $searchopt = [];
                  $tablename = '';
                  // It's not an internal device
                  foreach ($SEARCHOPTION as $key2 => $val2) {
                     if ($key2 == $data["id_search_option"]) {
                        $tmp['field'] =  $val2["name"];
                        $tablename    =  $val2["table"];
                        $fieldname    = $val2["field"];
                        $searchopt    = $val2;
                        if (isset($val2['datatype'])) {
                           $tmp['datatype'] = $val2["datatype"];
                        }
                        break;
                     }
                  }
                  if (($itemtable == $tablename)
                      || ($tmp['datatype'] == 'right')) {
                     switch ($tmp['datatype']) {
                        // specific case for text field
                        case 'text' :
                           $tmp['change'] = __('Update of the field');
                           break;
      
                        default :
                           $data["old_value"] = $item->getValueToDisplay($searchopt, $data["old_value"]);
                           $data["new_value"] = $item->getValueToDisplay($searchopt, $data["new_value"]);
                           break;
                     }
                  }
      
                  if (empty($tmp['change'])) {
                     $newval = $data["new_value"];
                     $oldval = $data["old_value"];
      
                     if ($data['id_search_option'] == '70') {
                        $newval = explode(' ', $newval);
                        $oldval = explode(' ', $oldval);
      
                        if ($oldval[0] == '&nbsp;') {
                           $oldval = $data["old_value"];
                        } else {
                           foreach ($DB->request('glpi_users', "`name` = '".$oldval[0]."'") as $val) {
                              $oldval = sprintf(__('%1$s %2$s'),
                                    formatUserName($val['id'], $oldval[0], $val['realname'],
                                          $val['firstname']),
                                    $oldval[1]);
                           }
                        }
      
                        if ($newval[0] == '&nbsp;') {
                           $newval = $data["new_value"];
                        } else {
                           foreach ($DB->request('glpi_users', "`name` = '".$newval[0]."'") as $val) {
                              $newval = sprintf(__('%1$s %2$s'),
                                    formatUserName($val['id'], $newval[0], $val['realname'],
                                          $val['firstname']),
                                    $newval[1]);
                           }
                        }
                     }
                     $tmp['change'] = sprintf(__('Change %1$s to %2$s'), $oldval, $newval);
                  }
               }
               $changes[] = $tmp;
            }
            return $changes;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (!$withtemplate) {
         $nb = 0;
         switch ($item->getType()) {
            case 'Ticket' :
               if (($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] != 0)
                   && (count($_SESSION["glpiactiveprofile"]["helpdesk_item_type"]) > 0)) {
                  if ($_SESSION['glpishow_count_on_tabs']) {
                     $nb = countElementsInTable('glpi_items_tickets',
                                                ['AND' => ['tickets_id' => $item->getID() ],
                                                   ['itemtype' => $_SESSION["glpiactiveprofile"]["helpdesk_item_type"]]
                                                ]);
                  }
                  return self::createTabEntry(_n('Item', 'Items', Session::getPluralNumber()), $nb);
               }
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      switch ($item->getType()) {
         case 'Ticket' :
            self::showForTicket($item);
            break;
      }
      return true;
   }

   /**
    * Make a select box for Tracking All Devices
    *
    * @param $myname             select name
    * @param $itemtype           preselected value.for item type
    * @param $items_id           preselected value for item ID (default 0)
    * @param $admin              is an admin access ? (default 0)
    * @param $users_id           user ID used to display my devices (default 0
    * @param $entity_restrict    Restrict to a defined entity (default -1)
    * @param $options   array of possible options:
    *    - tickets_id : ID of the ticket
    *    - used       : ID of the requester user
    *    - multiple   : allow multiple choice
    *    - rand       : random number
    *
    * @return nothing (print out an HTML select box)
   **/
   static function dropdownAllDevices($myname, $itemtype, $items_id = 0, $admin = 0, $users_id = 0,
                                      $entity_restrict = -1, $options = []) {
      global $CFG_GLPI, $DB;

      $params = ['tickets_id' => 0,
                      'used'       => [],
                      'multiple'   => 0,
                      'rand'       => mt_rand()];

      foreach ($options as $key => $val) {
         $params[$key] = $val;
      }

      $rand = $params['rand'];

      if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"] == 0) {
         echo "<input type='hidden' name='$myname' value=''>";
         echo "<input type='hidden' name='items_id' value='0'>";

      } else {
         echo "<div id='tracking_all_devices$rand'>";
         if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"]&pow(2,
                                                                     Ticket::HELPDESK_ALL_HARDWARE)) {
            // Display a message if view my hardware
            if ($users_id
                &&($_SESSION["glpiactiveprofile"]["helpdesk_hardware"]&pow(2,
                                                                           Ticket::HELPDESK_MY_HARDWARE))) {
               echo __('Or complete search')."&nbsp;";
            }

            $types = Ticket::getAllTypesForHelpdesk();
            $emptylabel = __('General');
            if ($params['tickets_id'] > 0) {
               $emptylabel = Dropdown::EMPTY_VALUE;
            }
            Dropdown::showItemTypes($myname, array_keys($types),
                                    ['emptylabel' => $emptylabel,
                                          'value'      => $itemtype,
                                          'rand'       => $rand, 'display_emptychoice' => true]);
            $found_type = isset($types[$itemtype]);

            $p = ['itemtype'        => '__VALUE__',
                       'entity_restrict' => $entity_restrict,
                       'admin'           => $admin,
                       'used'            => $params['used'],
                       'multiple'        => $params['multiple'],
                       'rand'            => $rand,
                       'myname'          => "add_items_id"];

            Ajax::updateItemOnSelectEvent("dropdown_$myname$rand", "results_$myname$rand",
                                          $CFG_GLPI["root_doc"].
                                             "/ajax/dropdownTrackingDeviceType.php",
                                          $p);
            echo "<span id='results_$myname$rand'>\n";
            // Display default value if itemtype is displayed
            if ($found_type
                && $itemtype) {
               if (($item = getItemForItemtype($itemtype))
                    && $items_id) {
                  if ($item->getFromDB($items_id)) {
                     Dropdown::showFromArray('items_id', [$items_id => $item->getName()],
                                             ['value' => $items_id]);
                  }
               } else {
                  $p['itemtype'] = $itemtype;
                  echo "<script type='text/javascript' >\n";
                  echo "$(function() {";
                  Ajax::updateItemJsCode("results_$myname$rand",
                                         $CFG_GLPI["root_doc"].
                                            "/ajax/dropdownTrackingDeviceType.php",
                                         $p);
                  echo '});</script>';
               }
            }
            echo "</span>\n";
         }
         echo "</div>";
      }
      return $rand;
   }

   /**
    * Make a select box for Ticket my devices
    *
    * @param $userID          User ID for my device section (default 0)
    * @param $entity_restrict restrict to a specific entity (default -1)
    * @param $itemtype        of selected item (default 0)
    * @param $items_id        of selected item (default 0)
    * @param $options   array of possible options:
    *    - used     : ID of the requester user
    *    - multiple : allow multiple choice
    *
    * @return nothing (print out an HTML select box)
   **/
   static function dropdownMyDevices($userID = 0, $entity_restrict = -1, $itemtype = 0, $items_id = 0, $options = []) {

      global $DB, $CFG_GLPI;
      $params = ['tickets_id' => 0,
                      'used'       => [],
                      'multiple'   => false,
                      'rand'       => mt_rand()];

      foreach ($options as $key => $val) {
         $params[$key] = $val;
      }

      if ($userID == 0) {
         $userID = Session::getLoginUserID();
      }

      $rand        = $params['rand'];
      $already_add = $params['used'];

      if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"]&pow(2, Ticket::HELPDESK_MY_HARDWARE)) {
         $my_devices = ['' => __('General')];
         if ($params['tickets_id'] > 0) {
            $my_devices = ['' => Dropdown::EMPTY_VALUE];
         }
         $devices    = [];

         // My items
         foreach ($CFG_GLPI["linkuser_types"] as $itemtype) {
            if (($item = getItemForItemtype($itemtype))
                && Ticket::isPossibleToAssignType($itemtype)) {
               $itemtable = getTableForItemType($itemtype);

               $query     = "SELECT *
                             FROM `$itemtable`
                             WHERE `users_id` = '$userID'";
               if ($item->maybeDeleted()) {
                  $query .= " AND `$itemtable`.`is_deleted` = '0' ";
               }
               if ($item->maybeTemplate()) {
                  $query .= " AND `$itemtable`.`is_template` = '0' ";
               }
               if (in_array($itemtype, $CFG_GLPI["helpdesk_visible_types"])) {
                  $query .= " AND `is_helpdesk_visible` = '1' ";
               }

               $query .= getEntitiesRestrictRequest("AND", $itemtable, "", $entity_restrict,
                                                    $item->maybeRecursive())."


                         ORDER BY `name` ";

               $result  = $DB->query($query);
               $nb      = $DB->numrows($result);
               if ($DB->numrows($result) > 0) {
                  $type_name = $item->getTypeName($nb);

                  while ($data = $DB->fetch_assoc($result)) {
                     if (!isset($already_add[$itemtype]) || !in_array($data["id"], $already_add[$itemtype])) {
                        $output = $data["name"];
                        if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                           $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                        }
                        $output = sprintf(__('%1$s - %2$s'), $type_name, $output);
                        if ($itemtype != 'Software') {
                           if (!empty($data['serial'])) {
                              $output = sprintf(__('%1$s - %2$s'), $output, $data['serial']);
                           }
                           if (!empty($data['otherserial'])) {
                              $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                           }
                        }
                        $devices[$itemtype."_".$data["id"]] = $output;

                        $already_add[$itemtype][] = $data["id"];
                     }
                  }
               }
            }
         }

         if (count($devices)) {
            $my_devices[__('My devices')] = $devices;
         }
         // My group items
         if (Session::haveRight("show_group_hardware", "1")) {
            $group_where = "";
            $query       = "SELECT `glpi_groups_users`.`groups_id`, `glpi_groups`.`name`
                            FROM `glpi_groups_users`
                            LEFT JOIN `glpi_groups`
                              ON (`glpi_groups`.`id` = `glpi_groups_users`.`groups_id`)
                            WHERE `glpi_groups_users`.`users_id` = '$userID' ".
                                  getEntitiesRestrictRequest("AND", "glpi_groups", "",
                                                             $entity_restrict, true);
            $result  = $DB->query($query);

            $first   = true;
            $devices = [];
            if ($DB->numrows($result) > 0) {
               while ($data = $DB->fetch_assoc($result)) {
                  if ($first) {
                     $first = false;
                  } else {
                     $group_where .= " OR ";
                  }
                  $a_groups                     = getAncestorsOf("glpi_groups", $data["groups_id"]);
                  $a_groups[$data["groups_id"]] = $data["groups_id"];
                  $group_where                 .= " `groups_id` IN (".implode(',', $a_groups).") ";
               }

               foreach ($CFG_GLPI["linkgroup_types"] as $itemtype) {
                  if (($item = getItemForItemtype($itemtype))
                      && Ticket::isPossibleToAssignType($itemtype)) {
                     $itemtable  = getTableForItemType($itemtype);
                     $query      = "SELECT *
                                    FROM `$itemtable`
                                    WHERE ($group_where) ".
                                          getEntitiesRestrictRequest("AND", $itemtable, "",
                                                                     $entity_restrict,
                                                                     $item->maybeRecursive());

                     if ($item->maybeDeleted()) {
                        $query .= " AND `is_deleted` = '0' ";
                     }
                     if ($item->maybeTemplate()) {
                        $query .= " AND `is_template` = '0' ";
                     }
                     $query .= ' ORDER BY `name`';

                     $result = $DB->query($query);
                     if ($DB->numrows($result) > 0) {
                        $type_name = $item->getTypeName();
                        if (!isset($already_add[$itemtype])) {
                           $already_add[$itemtype] = [];
                        }
                        while ($data = $DB->fetch_assoc($result)) {
                           if (!in_array($data["id"], $already_add[$itemtype])) {
                              $output = '';
                              if (isset($data["name"])) {
                                 $output = $data["name"];
                              }
                              if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                                 $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                              }
                              $output = sprintf(__('%1$s - %2$s'), $type_name, $output);
                              if (isset($data['serial'])) {
                                 $output = sprintf(__('%1$s - %2$s'), $output, $data['serial']);
                              }
                              if (isset($data['otherserial'])) {
                                 $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                              }
                              $devices[$itemtype."_".$data["id"]] = $output;

                              $already_add[$itemtype][] = $data["id"];
                           }
                        }
                     }
                  }
               }
               if (count($devices)) {
                  $my_devices[__('Devices own by my groups')] = $devices;
               }
            }
         }
         // Get linked items to computers
         if (isset($already_add['Computer']) && count($already_add['Computer'])) {
            $search_computer = " XXXX IN (".implode(',', $already_add['Computer']).') ';
            $devices = [];

            // Direct Connection
            $types = ['Monitor', 'Peripheral', 'Phone', 'Printer'];
            foreach ($types as $itemtype) {
               if (in_array($itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])
                   && ($item = getItemForItemtype($itemtype))) {
                  $itemtable = getTableForItemType($itemtype);
                  if (!isset($already_add[$itemtype])) {
                     $already_add[$itemtype] = [];
                  }
                  $query = "SELECT DISTINCT `$itemtable`.*
                            FROM `glpi_computers_items`
                            LEFT JOIN `$itemtable`
                                 ON (`glpi_computers_items`.`items_id` = `$itemtable`.`id`)
                            WHERE `glpi_computers_items`.`itemtype` = '$itemtype'
                                  AND  ".str_replace("XXXX", "`glpi_computers_items`.`computers_id`",
                                                     $search_computer);
                  if ($item->maybeDeleted()) {
                     $query .= " AND `$itemtable`.`is_deleted` = '0' ";
                  }
                  if ($item->maybeTemplate()) {
                     $query .= " AND `$itemtable`.`is_template` = '0' ";
                  }
                  $query .= getEntitiesRestrictRequest("AND", $itemtable, "", $entity_restrict)."
                            ORDER BY `$itemtable`.`name`";

                  $result = $DB->query($query);
                  if ($DB->numrows($result) > 0) {
                     $type_name = $item->getTypeName();
                     while ($data = $DB->fetch_assoc($result)) {
                        if (!in_array($data["id"], $already_add[$itemtype])) {
                           $output = $data["name"];
                           if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                              $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                           }
                           $output = sprintf(__('%1$s - %2$s'), $type_name, $output);
                           if ($itemtype != 'Software') {
                              $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                           }
                           $devices[$itemtype."_".$data["id"]] = $output;

                           $already_add[$itemtype][] = $data["id"];
                        }
                     }
                  }
               }
            }
            if (count($devices)) {
               $my_devices[__('Connected devices')] = $devices;
            }

            // Software
            if (in_array('Software', $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
               $query = "SELECT DISTINCT `glpi_softwareversions`.`name` AS version,
                                `glpi_softwares`.`name` AS name, `glpi_softwares`.`id`
                         FROM `glpi_computers_softwareversions`, `glpi_softwares`,
                              `glpi_softwareversions`
                         WHERE `glpi_computers_softwareversions`.`softwareversions_id` =
                                   `glpi_softwareversions`.`id`
                               AND `glpi_softwareversions`.`softwares_id` = `glpi_softwares`.`id`
                               AND ".str_replace("XXXX",
                                                 "`glpi_computers_softwareversions`.`computers_id`",
                                                 $search_computer)."
                               AND `glpi_softwares`.`is_helpdesk_visible` = '1' ".
                               getEntitiesRestrictRequest("AND", "glpi_softwares", "",
                                                          $entity_restrict)."
                         ORDER BY `glpi_softwares`.`name`";
               $devices = [];
               $result = $DB->query($query);
               if ($DB->numrows($result) > 0) {
                  $tmp_device = "";
                  $item       = new Software();
                  $type_name  = $item->getTypeName();
                  if (!isset($already_add['Software'])) {
                     $already_add['Software'] = [];
                  }
                  while ($data = $DB->fetch_assoc($result)) {
                     if (!in_array($data["id"], $already_add['Software'])) {
                        $output = sprintf(__('%1$s - %2$s'), $type_name, $data["name"]);
                        $output = sprintf(__('%1$s (%2$s)'), $output,
                                          sprintf(__('%1$s: %2$s'), __('version'),
                                                  $data["version"]));
                        if ($_SESSION["glpiis_ids_visible"]) {
                           $output = sprintf(__('%1$s (%2$s)'), $output, $data["id"]);
                        }
                        $devices["Software_".$data["id"]] = $output;

                        $already_add['Software'][] = $data["id"];
                     }
                  }
                  if (count($devices)) {
                     $my_devices[__('Installed software')] = $devices;
                  }
               }
            }
         }
         echo "<div id='tracking_my_devices'>";
         Dropdown::showFromArray('my_items', $my_devices, ['rand' => $rand]);
         echo "</div>";

         // Auto update summary of active or just solved tickets
         $params = ['my_items' => '__VALUE__'];

         Ajax::updateItemOnSelectEvent("dropdown_my_items$rand", "item_ticket_selection_information",
                                       $CFG_GLPI["root_doc"]."/ajax/ticketiteminformation.php",
                                       $params);
      }
   }

   /**
    * Make a select box with all glpi items
    *
    * @param $options array of possible options:
    *    - name         : string / name of the select (default is users_id)
    *    - value
    *    - comments     : boolean / is the comments displayed near the dropdown (default true)
    *    - entity       : integer or array / restrict to a defined entity or array of entities
    *                      (default -1 : no restriction)
    *    - entity_sons  : boolean / if entity restrict specified auto select its sons
    *                      only available if entity is a single value not an array(default false)
    *    - rand         : integer / already computed rand value
    *    - toupdate     : array / Update a specific item on select change on dropdown
    *                      (need value_fieldname, to_update, url
    *                      (see Ajax::updateItemOnSelectEvent for information)
    *                      and may have moreparams)
    *    - used         : array / Already used items ID: not to display in dropdown (default empty)
    *    - on_change    : string / value to transmit to "onChange"
    *    - display      : boolean / display or get string (default true)
    *    - width        : specific width needed (default 80%)
    *
   **/
   static function dropdown($options = []) {
      global $DB;

      // Default values
      $p['name']           = 'items';
      $p['value']          = '';
      $p['all']            = 0;
      $p['on_change']      = '';
      $p['comments']       = 1;
      $p['width']          = '80%';
      $p['entity']         = -1;
      $p['entity_sons']    = false;
      $p['used']           = [];
      $p['toupdate']       = '';
      $p['rand']           = mt_rand();
      $p['display']        = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $itemtypes = ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer'];

      $query = "";
      foreach ($itemtypes as $type) {
         $table = getTableForItemType($type);
         if (!empty($query)) {
            $query .= " UNION ";
         }
         $query .= " SELECT `$table`.`id` AS id , '$type' AS itemtype , `$table`.`name` AS name
                     FROM `$table`
                     WHERE `$table`.`id` IS NOT NULL AND `$table`.`is_deleted` = '0' AND `$table`.`is_template` = '0' ";
      }

      $result = $DB->query($query);
      $output = [];
      if ($DB->numrows($result) > 0) {
         while ($data = $DB->fetch_assoc($result)) {
            $item = getItemForItemtype($data['itemtype']);
            $output[$data['itemtype']."_".$data['id']] = $item->getTypeName()." - ".$data['name'];
         }
      }

      return Dropdown::showFromArray($p['name'], $output, $p);
   }

   /**
    * Return used items for a ticket
    *
    * @param type $tickets_id
    * @return type
    */
   static function getUsedItems($tickets_id) {

      $data = getAllDatasFromTable('glpi_items_tickets', " `tickets_id` = ".$tickets_id);
      $used = [];
      if (!empty($data)) {
         foreach ($data as $val) {
            $used[$val['itemtype']][] = $val['items_id'];
         }
      }

      return $used;
   }

   /**
    * Form for Followup on Massive action
   **/
   static function showFormMassiveAction($ma) {
      global $CFG_GLPI;

      switch ($ma->getAction()) {
         case 'add_item' :
            Dropdown::showSelectItemFromItemtypes(['items_id_name'   => 'items_id',
                                                   'itemtype_name'   => 'item_itemtype',
                                                   'itemtypes'       => $CFG_GLPI['ticket_types'],
                                                   'checkright'      => true,
                                                   'entity_restrict' => $_SESSION['glpiactive_entity']
                                                  ]);
            echo "<br><input type='submit' name='add' value=\""._sx('button', 'Add')."\" class='submit'>";
            break;

         case 'delete_item' :
            Dropdown::showSelectItemFromItemtypes(['items_id_name'   => 'items_id',
                                                   'itemtype_name'   => 'item_itemtype',
                                                   'itemtypes'       => $CFG_GLPI['ticket_types'],
                                                   'checkright'      => true,
                                                   'entity_restrict' => $_SESSION['glpiactive_entity']
                                                  ]);

            echo "<br><input type='submit' name='delete' value=\"".__('Delete permanently')."\" class='submit'>";
            break;
      }

   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
   **/
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case 'add_item' :
            static::showFormMassiveAction($ma);
            return true;

         case 'delete_item' :
            static::showFormMassiveAction($ma);
            return true;
      }

      return parent::showMassiveActionsSubForm($ma);
   }


   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {

      switch ($ma->getAction()) {
         case 'add_item' :
            $input = $ma->getInput();

            $item_ticket = new static();
            foreach ($ids as $id) {
               if ($item->getFromDB($id) && !empty($input['items_id'])) {
                  $input['tickets_id'] = $id;
                  $input['itemtype'] = $input['item_itemtype'];

                  if ($item_ticket->can(-1, CREATE, $input)) {
                     $ok = true;
                     if (!$item_ticket->add($input)) {
                        $ok = false;
                     }

                     if ($ok) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                     } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                     }

                  } else {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                     $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                  }

               } else {
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                  $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
               }
            }
            return;

         case 'delete_item' :
            $input = $ma->getInput();
            $item_ticket = new static();
            foreach ($ids as $id) {
               if ($item->getFromDB($id) && !empty($input['items_id'])) {
                  $item_found = $item_ticket->find("`tickets_id` = $id AND `itemtype` = '".$input['item_itemtype']."' AND `items_id` = ".$input['items_id']);
                  if (!empty($item_found)) {
                     $item_founds_id = array_keys($item_found);
                     $input['id'] = $item_founds_id[0];

                     if ($item_ticket->can($input['id'], DELETE, $input)) {
                        $ok = true;
                        if (!$item_ticket->delete($input)) {
                           $ok = false;
                        }

                        if ($ok) {
                           $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                           $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                           $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                        }

                     } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                     }

                  } else {
                     $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                     $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                  }

               } else {
                  $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                  $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
               }
            }
            return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }

   function getSearchOptionsNew() {
      $tab = [];

      $tab[] = [
         'id'                 => '13',
         'table'              => $this->getTable(),
         'field'              => 'items_id',
         'name'               => _n('Associated element', 'Associated elements', 2),
         'datatype'           => 'specific',
         'comments'           => true,
         'nosort'             => true,
         'additionalfields'   => ['itemtype']
      ];

      $tab[] = [
         'id'                 => '131',
         'table'              => $this->getTable(),
         'field'              => 'itemtype',
         'name'               => _n('Associated item type', 'Associated item types', 2),
         'datatype'           => 'itemtypename',
         'itemtype_list'      => 'ticket_types',
         'nosort'             => true
      ];

      return $tab;
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $values
    * @param $options   array
   **/
   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'items_id':
            if (strpos($values[$field], "_") !== false) {
               $item_itemtype      = explode("_", $values[$field]);
               $values['itemtype'] = $item_itemtype[0];
               $values[$field]     = $item_itemtype[1];
            }

            if (isset($values['itemtype'])) {
               if (isset($options['comments']) && $options['comments']) {
                  $tmp = Dropdown::getDropdownName(getTableForItemtype($values['itemtype']),
                                                   $values[$field], 1);
                  return sprintf(__('%1$s %2$s'), $tmp['name'],
                                 Html::showToolTip($tmp['comment'], ['display' => false]));

               }
               return Dropdown::getDropdownName(getTableForItemtype($values['itemtype']),
                                                $values[$field]);
            }
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $name            (default '')
    * @param $values          (default '')
    * @param $options   array
    *
    * @return string
   **/
   static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;
      switch ($field) {
         case 'items_id' :
            if (isset($values['itemtype']) && !empty($values['itemtype'])) {
               $options['name']  = $name;
               $options['value'] = $values[$field];
               return Dropdown::show($values['itemtype'], $options);
            } else {
               self::dropdownAllDevices($name, 0, 0);
               return ' ';
            }
            break;
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }

   /**
    * Add a message on add action
   **/
   function addMessageOnAddAction() {
      global $CFG_GLPI;

      $addMessAfterRedirect = false;
      if (isset($this->input['_add'])) {
         $addMessAfterRedirect = true;
      }

      if (isset($this->input['_no_message'])
          || !$this->auto_message_on_action) {
         $addMessAfterRedirect = false;
      }

      if ($addMessAfterRedirect) {
         $item = getItemForItemtype($this->fields['itemtype']);
         $item->getFromDB($this->fields['items_id']);

         $link = $item->getFormURL();
         if (!isset($link)) {
            return;
         }
         if (($name = $item->getName()) == NOT_AVAILABLE) {
            //TRANS: %1$s is the itemtype, %2$d is the id of the item
            $item->fields['name'] = sprintf(__('%1$s - ID %2$d'),
                                            $item->getTypeName(1), $item->fields['id']);
         }

         $display = (isset($this->input['_no_message_link'])?$item->getNameID()
                                                            :$item->getLink());

         // Do not display quotes
         //TRANS : %s is the description of the added item
         Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Item successfully added'),
                                                  stripslashes($display)));

      }
   }

   /**
    * Add a message on delete action
   **/
   function addMessageOnPurgeAction() {

      if (!$this->maybeDeleted()) {
         return;
      }

      $addMessAfterRedirect = false;
      if (isset($this->input['_delete'])) {
         $addMessAfterRedirect = true;
      }

      if (isset($this->input['_no_message'])
          || !$this->auto_message_on_action) {
         $addMessAfterRedirect = false;
      }

      if ($addMessAfterRedirect) {
         $item = getItemForItemtype($this->fields['itemtype']);
         $item->getFromDB($this->fields['items_id']);

         $link = $item->getFormURL();
         if (!isset($link)) {
            return;
         }
         if (isset($this->input['_no_message_link'])) {
            $display = $item->getNameID();
         } else {
            $display = $item->getLink();
         }
         //TRANS : %s is the description of the updated item
         Session::addMessageAfterRedirect(sprintf(__('%1$s: %2$s'), __('Item successfully deleted'), $display));

      }
   }
}
