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
      <?php  
         $numero_lacre = '';
         for ($i=0; $i < $contador; $i++) { 
               foreach ($data as  $value) {
                 
                  $consulta_lacre = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = ".$value[$i]);
                  if($consulta_lacre->num_rows>0){
                     foreach($consulta_lacre as $t){
                        $numero_lacre = $t['lacre_number'];
                     }
                  }
         ?>
        <tr class="noHover">
            <th colspan="8">
                  <label>Id do computador:  </label>
                   <input type="text" readonly name="id_computador[]" value="<?php echo  $value[$i]; ?>"> 
            </th>
            <th colspan="8">
                  <label>Número do lacre:  </label>
                   <input type="text" required name="numero_lacre[]" value="<?php echo $numero_lacre;?>"> 
            </th>
        </tr>
      <?php  } } ?>
        <tr class="noHover">
        <th colspan="16">
            <input type="hidden" name="ticke_id" value="<?=$ticket_id?>">
         <?php 
            if(!empty($numero_lacre)){
            ?>
            <input type="submit" value="ValidarLacre" name="validar" class="submit">
            <input type="submit" value="Alterar Lacre" name="alterar" class="submit">
         <?php 
            }else{
            ?>
            <input type="submit" value="Cadastrar Lacre" name="cadastro" class="submit">
         <?php 
            }
            ?>
        </th>
    </tr>
        </tbody>
        </table>
   </form>
   <?php
   if (isset($_POST["cadastro"]) || isset($_POST["validar"]) || isset($_POST["alterar"])) {
      $username =  $_SESSION['glpiname'];
      $userid = $_SESSION['glpiID'];
      $id_ticket = $_POST['ticke_id'];
      $today = date("Y-m-d H:i:s");  
      /* 
         Id de status
         0 - Sem lacre
         1 - Primeiro lacre
         2 - Validado lacre (lacre ja existente)
         3 - Lacre alterado
      */
      foreach($_POST['id_computador'] as $key => $v){ $computador[$key] = $v;}  
      foreach ($_POST['numero_lacre'] as $key => $v){ $lacre[$key] = $v; }
      $data = array_combine($computador, $lacre);
      $validata = array_combine($computador, $lacre);
      //Verifica se ja está com lacre cadastrado pelo id do computador
      $la = 1234567;
      foreach ($data as $key => $value) {
      $number_lacre[] = intval($value);
      $result = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = $key and status=1");
      }
      $cont = ($result->num_rows);
      foreach ($number_lacre as $key => $la) {
       $lacre  = $DB->query("select * from glpi_computer_lacre_hystori where (lacre_number = $la)
       or (lacre_number = $la and status=1)
       or (lacre_number = $la and status=2)
       or (lacre_number = $la and status=3)");
      }
      $lacretotal = ($lacre->num_rows);
      
      
      /*Cadastrar lacre*/
      if(isset($_POST["cadastro"])){
         

        




         
        
       
         if ($cont == 0) { 
            //Incio do lacre
            foreach ($data as $key => $value) {
               if ( !is_numeric($value) ) {
                 
               
                  $lacre_missing["digito"] = 'O número do lacre deve conter apenas números';
                  $message = sprintf(__('Por favor corrija: %s'),
                  implode(", ", $lacre_missing));
                  Session::addMessageAfterRedirect($message, false, ERROR);
                  Html::redirect($CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php?ticket_id=".$id_ticket);
                 
              } elseif (strlen($value) != 7 ) {
                  # igual a sete
                  $lacre_missing["digito"] = 'O número do lacre deve conter 7 númerais';
                  $message = sprintf(__('Por favor corrija: %s'),
                  implode(", ", $lacre_missing));
                  Session::addMessageAfterRedirect($message, false, ERROR);
                  Html::redirect($CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php?ticket_id=".$id_ticket);
                  
              } else {
               if ($lacretotal == 0 ) {
                  #root
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
                     id_ticket = $id_ticket,
                     username = '$username',
                     user_id_alter = '$userid',
                     data_alteracao = '$today'
                     ";
                     $DB->query($hystori);
                     $DB->query($insere_lacre);
                } else {
                 
                  $lacre_missing["digito"] = 'Esse número de lacre já foi usado!';
                  $message = sprintf(__('Por favor corrija: %s'),
                  implode(", ", $lacre_missing));
                  Session::addMessageAfterRedirect($message, false, ERROR);
                  Html::redirect($CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php?ticket_id=".$id_ticket);


                }
                  
              }
               
            }
         }
         Html::redirect("{$CFG_GLPI['root_doc']}/front/ticket.form.php?id=$id_ticket");
         //Fim do lacre
      } 
      /*Validar Lacre*/
      else if(isset($_POST["validar"])){
         if ($cont > 0) { 
            foreach($result as $registro_atual){
               $verifica_lacre = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = '$key' AND lacre_number = '$value'");
               $cont_lacre = ($verifica_lacre->num_rows);
               if ($cont_lacre > 0) {
                  $hystori = "
                        UPDATE glpi_computer_lacre_hystori 
                        SET status = 2,
                        computer_id = '$key',
                        username = '$username',
                        user_id_alter = '$userid',
                        data_alteracao = '$today' ,
                        id_ticket = $id_ticket,
                        WHERE id=".$registro_atual['id'];
                  $DB->query($hystori);
                  $DB->query($atualiza_lacre);
                  $hystori = "
                     INSERT INTO glpi_computer_lacre_hystori SET
                     computer_id = '$key',
                     lacre_number = '$value',
                     status = 2,
                     username = '$username',
                     id_ticket = $id_ticket,
                     user_id_alter = '$userid',
                     data_alteracao = '$today'
                     ";
                     $DB->query($hystori);
                     $DB->query($insere_lacre);
               }else{
                  $lacre_missing["nostring"] = 'Lacre diferente do cadastrado';
                  $message = sprintf(__('Por favor corrija: %s'),
                  implode(", ", $lacre_missing));
                  Session::addMessageAfterRedirect($message, false, ERROR);
                  Html::redirect($CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php?ticket_id=".$id_ticket);
               }
            }
            
         }
        // Html::redirect("{$CFG_GLPI['root_doc']}/front/computer.form.php?id=$key");
        Html::redirect("{$CFG_GLPI['root_doc']}/front/ticket.form.php?id=$id_ticket");
         
      }
      else if(isset($_POST["alterar"])){
         if ($cont > 0) { 
            $verifica_lacre = $DB->query("select * from glpi_computer_lacre_hystori where lacre_number = '$value'");
            $cont_lacre = ($verifica_lacre->num_rows);
            if ($cont_lacre == 0) { 
               foreach($result as $registro_atual){
                  $hystori = "
                        UPDATE glpi_computer_lacre_hystori 
                        SET status = 3,
                        computer_id = 909090909,
                        username = '$username',
                        user_id_alter = '$userid',
                        data_alteracao = '$today' ,
                        WHERE id=".$registro_atual['id'];
                      
                  $hystori = "
                     INSERT INTO glpi_computer_lacre_hystori SET
                     computer_id = '$key',
                     lacre_number = '$value',
                     status = 3,
                     username = '$username',
                     user_id_alter = '$userid',
                     id_ticket = $id_ticket,
                     data_alteracao = '$today'
                     ";
                     $DB->query($hystori);
                     $DB->query($insere_lacre);
               }
            }else{
               
               $lacre_missing["nostring"] = 'Esse lacre já existe';
               $message = sprintf(__('Por favor corrija: %s'),
               implode(", ", $lacre_missing));
               Session::addMessageAfterRedirect($message, false, ERROR);
               Html::redirect($CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php?ticket_id=".$id_ticket);
            }
            Html::redirect("{$CFG_GLPI['root_doc']}/front/ticket.form.php?id=$id_ticket");
         }
      
         
      }
    

                  
                               
    }

   //  if (isset($_POST["cadastrosemticket"])) {
   //    $id_computador = ($_POST['idcomputador']);
   //    $lacre = ($_POST['lacrenumber']);
   //    $username =  $_SESSION['glpiname'];
   //    $userid = $_SESSION['glpiID'];
   //    $today = date("Y-m-d H:i:s");

   //    $result = $DB->query("select * from glpi_computer_lacre_hystori where computer_id =$id_computador");
   //    $cont = ($result->num_rows);
       
   //       /* 
   //    Id de status
   //    0 - Sem lacre
   //    1 - Alterado via plugin via tela de chamado
   //    10 - Alterado via plugin via tela de ativo
   //    */
   //    $insere_lacre = "
   //                INSERT INTO glpi_computers_lacre SET
   //                computer_id = '$id_computador',
   //                status = 10,
   //                nlacre ='$lacre',
   //                id_ticket = ''
   //                ";
   //    $hystori = "
   //                INSERT INTO glpi_computer_lacre_hystori SET
   //                computer_id = '$id_computador',
   //                lacre_number = '$lacre',
   //                status = 10,
   //                username = '$username',
   //                user_id_alter = '$userid',
   //                data_alteracao = '$today'
   //                ";
   //                $DB->query($hystori);
   //                $DB->query($insere_lacre);
   //                Html::redirect("{$CFG_GLPI['root_doc']}/front/computer.form.php?id=$id_computador");
   //                // if($DB->query($hystori) && $DB->query($insere_lacre) ){
   //                //    echo $id_computador;
   //                //   // Html::redirect("{$CFG_GLPI['root_doc']}/front/computer.form.php?id=$id_computador");
   //                // }
                 
      
      
   //  }



    





   
  

    

 

   

   




  
     
        
