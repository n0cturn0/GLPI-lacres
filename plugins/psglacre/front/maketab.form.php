<?php
include ("../../../inc/includes.php");

   $id = $_GET['ticket_id'];  
   if(!empty( $_GET['ticket_id']))
   {
      $ticket_id = intval((filter_var($id, FILTER_SANITIZE_NUMBER_INT)));
  }
   global $DB;
   $result = $DB->query("SELECT * FROM glpi_items_tickets WHERE tickets_id='$ticket_id'");
    if ($result->num_rows == 0) {
       
    } else {
      foreach($result as $value){ 
         $data['ticketid'][] = $value['items_id'];
      }
      $contador = count($data['ticketid']);
    }
    
    $config = PluginPsglacreMaketab::getConfig();
    Html::header( __('Perception', 'perception'),
    $_SERVER['PHP_SELF'],
    'assets',
     PluginPsglacreMaketab::class);
     


   echo "<form action='".$CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php' method='post'>";
     ?>
    <table class="tab_cadre_fixehov">
        <tbody>
         <?php  for ($i=0; $i < $contador; $i++) { 
               foreach ($data as  $value) {
         ?>
        <tr class="noHover">
            <th colspan="8">
                  <label>Id do computador:  </label>
                   <input type="text" name="id_computador[]" value="<?php echo  $value[$i]; ?>"> 
            </th>
            <th colspan="8">
                  <label>Número do lacre:  </label>
                   <input type="text" name="numero_lacre[]" value=""> 
            </th>
        </tr>
               <?php 
               } 
            }
               ?>
        <tr class="noHover">
        <th colspan="8">
            <input type="hidden" name="ticke_id" value="<?=$ticket_id?>">
        <input type="submit" value="Cadastrar Lacre" name="cadastro" class="submit">
        </th>
    </tr>
        </tbody>
        </table>
   </form>
   <?php
    if (isset($_POST["cadastro"])) {
   $username =  $_SESSION['glpiname'];
   $userid = $_SESSION['glpiID'];
   $id_ticket = $_POST['ticke_id'];
   $today = date("Y-m-d H:i:s");  
   /* 
      Id de status
      0 - Sem lacre
      1 - Alterado via plugin via tela de chamado
      10 - Alterado via plugin via tela de ativo
   */
  

   foreach($_POST['id_computador'] as $key => $v){ $computador[$key] = $v;}  
   foreach ($_POST['numero_lacre'] as $key => $v){ $lacre[$key] = $v; }
   $data = array_combine($computador, $lacre);
   //Verifica se ja está com lacre cadastrado
   foreach ($data as $key => $value) {
   $result = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = $key");
   }
   $cont = ($result->num_rows);
      if ($cont == 0) { 
        //Incio do lacre
        foreach ($data as $key => $value) {
         $insere_lacre = "
      INSERT INTO glpi_computers_lacre SET
      computer_id = '$key',
      status = 1,
      nlacre ='$value',
      id_ticket = $id_ticket
      ";

      $hystori = "
         INSERT INTO glpi_computer_lacre_hystori SET
         computer_id = '$key',
         lacre_number = '$value',
         status = 1,
         username = '$username',
         user_id_alter = '$userid',
         data_alteracao = '$today'
         ";
         
         if( $DB->query($hystori) && $DB->query($insere_lacre)){
            Html::redirect("{$CFG_GLPI['root_doc']}/front/ticket.form.php?id=$id_ticket");
         }
         
      }
        //Fim do lacre
        
      } else {
         Html::redirect("{$CFG_GLPI['root_doc']}/front/computer.form.php?id=$key");
         
         // echo 'Ja existe uma lacre';
         // exit();
      }

    

                  
                               
    }

    if (isset($_POST["cadastrosemticket"])) {
      $id_computador = ($_POST['idcomputador']);
      $lacre = ($_POST['lacrenumber']);
      $username =  $_SESSION['glpiname'];
      $userid = $_SESSION['glpiID'];
      $today = date("Y-m-d H:i:s");

      $result = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = $id_computador");
      $cont = ($result->num_rows);
       
         /* 
      Id de status
      0 - Sem lacre
      1 - Alterado via plugin via tela de chamado
      10 - Alterado via plugin via tela de ativo
      */
      $insere_lacre = "
                  INSERT INTO glpi_computers_lacre SET
                  computer_id = '$id_computador',
                  status = 10,
                  nlacre ='$lacre',
                  id_ticket = ''
                  ";
      $hystori = "
                  INSERT INTO glpi_computer_lacre_hystori SET
                  computer_id = '$id_computador',
                  lacre_number = '$lacre',
                  status = 10,
                  username = '$username',
                  user_id_alter = '$userid',
                  data_alteracao = '$today'
                  ";
                  if($DB->query($hystori) && $DB->query($insere_lacre) ){
                     Html::redirect("{$CFG_GLPI['root_doc']}/front/computer.php");
                  }
                 
      
      
    }



    





   
  

    

 

   

   




  
     
        
