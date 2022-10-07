<?php
include ("../../../inc/includes.php");
$id = $_GET['ticket_id'];  
if(!empty( $_GET['ticket_id']))
{
   $ticket_id = intval((filter_var($id, FILTER_SANITIZE_NUMBER_INT)));
}
global $DB;
$result = $DB->query("SELECT * FROM glpi_items_tickets WHERE tickets_id='$ticket_id'");
if ($result->num_rows != 0) {
   foreach($result as $value){ 
       $data['ticketid'][] = $value['items_id'];
   }
   $contador = count($data['ticketid']);
}
$config = PluginPsglacreMaketab::getConfig();
Html::header( __('Perception', 'perception'),
$_SERVER['PHP_SELF'],'assets',PluginPsglacreMaketab::class);
echo "<form id='formLacre' action='".$CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php' method='post'>";
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
               <th colspan="<?php echo (!empty($numero_lacre))?'4':'8';?>">
                     <label>Id do computador:  </label>
                        <input type="text" readonly name="id_computador[]" value="<?php echo  $value[$i]; ?>"> 
               </th>
            <?php if(!empty($numero_lacre)){?>
               <th colspan="4">
                  <input id="validar_<?php echo $value[$i];?>" checked type="radio" name="acao_lacre[<?php echo  $value[$i]; ?>]" value="validar" class="acao_lacre" >
                  <label for="validar_<?php echo $value[$i];?>">Validar</label>
                  <input id="alterar_<?php echo $value[$i];?>" type="radio" name="acao_lacre[<?php echo  $value[$i]; ?>]" value="alterar" class="acao_lacre">
                  <label for="alterar_<?php echo $value[$i];?>">Alterar</label>
               </th>
            <?php }?>
               <th colspan="<?php echo (!empty($numero_lacre))?'4':'8';?>">
                  <label>Número do lacre:  </label>
                  <input type="number" required="" minlength="7" maxlength="7"  name="numero_lacre[]"   value="<?php echo $numero_lacre;?>" class="numero_lacre"  <?php echo (!empty($numero_lacre))?' readonly="readonly" style="background-color: rgb(224, 224, 224);"':'';?>>
               </th>
            </tr>
   <?php 
            } 
      } 
      ?>
         <tr class="noHover">
            <th colspan="16">
               <input type="hidden" name="ticke_id" value="<?=$ticket_id?>">
            <?php 
               if(!empty($numero_lacre)){
               ?>
               
               <input type="submit" value="Salvar" name="salvar" class="submit">
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
   if (isset($_POST["cadastro"]) || isset($_POST["salvar"])) {
      $username =  $_SESSION['glpiname'];
      $userid = $_SESSION['glpiID'];
      $id_ticket = $_POST['ticke_id'];
      $today = date("Y-m-d H:i:s");  
      $error = false;
      /* 
         Id de status       
         1 - Primeiro lacre
         2 - Validado lacre (lacre ja existente)
         3 - Lacre alterado
      */
      foreach($_POST['id_computador'] as $key => $v){ $computador[$key] = $v;}  
      foreach ($_POST['numero_lacre'] as $key => $v){ $lacre[$key] = $v; }
      $data = array_combine($computador, $lacre);
      $validata = array_combine($computador, $lacre);
      //Verifica se ja está com lacre cadastrado pelo id do computador
      foreach ($data as $key => $value) {
      $number_lacre[] = intval($value);
      $result = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = $key ");
      }
      $cont = ($result->num_rows);
      foreach ($number_lacre as $key => $la) {
       $lacre  = $DB->query("select * from glpi_computer_lacre_hystori where (lacre_number = $la)
       or (lacre_number = $la and status=1)
       or (lacre_number = $la and status=2)
       or (lacre_number = $la and status=3)");
      }
      /*Cadastrar lacre*/
      if(isset($_POST["cadastro"])){
         foreach ($data as $key => $value) {
            if ( !is_numeric($value) ) {   
               $error = true; 
               $lacre_missing["digito"] .= 'O número do lacre deve conter apenas números<br>';
               $message = sprintf(__('Por favor corrija: %s'),
               implode(", ", $lacre_missing));  
            } elseif (strlen($value) != 7 ) {
               # igual a sete
               $error = true; 
               $lacre_missing["digito"] .= 'O número do lacre deve conter 7 númerais<br>';
               $message = sprintf(__('Por favor corrija: %s'),
               implode(", ", $lacre_missing));           
            } 
         }
      
      } else if(isset($_POST["salvar"])){
         foreach($_POST['acao_lacre'] as $computer_id=>$acao){
            if($acao == "validar"){
               $verifica_lacre = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = '".$computer_id."' AND lacre_number = '".$data[$computer_id]."'");
               $cont_lacre = ($verifica_lacre->num_rows);
               if ($cont_lacre > 0) {
               foreach($verifica_lacre as $registro_atual){  
                  if ( !is_numeric($registro_atual['lacre_number']) ) {
                     $error = true;         
                     $lacre_missing["digito"] .= 'O número do lacre deve conter apenas números<br>';
                     $message = sprintf(__('Por favor corrija: %s'),
                     implode(", ", $lacre_missing));   
                                 
                  } elseif (strlen($value) != 7 ) {
                     # igual a sete
                     $lacre_missing["digito"] .= 'O número do lacre deve conter 7 númerais<br>';
                     $message = sprintf(__('Por favor corrija: %s'),
                     implode(", ", $lacre_missing));   
                  } 
               }
               }else{
                  $error = true; 
                  $lacre_missing["nostring"] .= 'Lacre diferente do cadastrado<br>';
                  $message = sprintf(__('Por favor corrija: %s'),
                  implode(", ", $lacre_missing));      
               }      
            }else if($acao == "alterar"){
               if ($cont > 0) {                                     
                  $verifica_lacre = $DB->query("select * from glpi_computer_lacre_hystori where lacre_number = '".$data[$computer_id]."'");
                  $cont_lacre = ($verifica_lacre->num_rows);
                  if ($cont_lacre != 0) {
                     $error = true;         
                     $lacre_missing["nostring"] .= 'Esse lacre já existe<br>';
                     $message = sprintf(__('Por favor corrija: %s'),
                     implode(", ", $lacre_missing));                 
                  } 
               }            
            }            
         }                               
      }
      if($error){ 
         Session::addMessageAfterRedirect($message, false, ERROR);
         Html::redirect($CFG_GLPI["root_doc"]."/plugins/psglacre/front/maketab.form.php?ticket_id=".$id_ticket);                  
      } else {
         if (isset($_POST["cadastro"])) {
            foreach ($data as $key => $value) {
               $insere_lacre = "
               INSERT INTO glpi_computers_lacre SET
               computer_id = '$key',
               status = 1,
               nlacre ='$value',
               id_ticket = $id_ticket
               ";
               $DB->query($insere_lacre);                     
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
            }
         }else if(isset($_POST["salvar"])){
            foreach($_POST['acao_lacre'] as $computer_id=>$acao){
               if($acao == "validar"){
                  $verifica_lacre = $DB->query("select * from glpi_computer_lacre_hystori where computer_id = '".$computer_id."' AND lacre_number = '".$data[$computer_id]."'");
                  $cont_lacre = ($verifica_lacre->num_rows);
                  if ($cont_lacre > 0) {
                     foreach($verifica_lacre as $registro_atual){
                        $hystori = "
                        UPDATE glpi_computer_lacre_hystori 
                        SET status = 1,
                        username = '$username',
                        user_id_alter = '$userid',
                        data_alteracao = '$today' ,
                        WHERE id=".$registro_atual['id'];
                        $DB->query($hystori);

                        $hystori = "
                        INSERT INTO glpi_computer_lacre_hystori SET
                        computer_id = '".$registro_atual['computer_id']."',
                        lacre_number = '".$registro_atual['lacre_number']."',
                        status = 2,
                        username = '$username',
                        id_ticket = $id_ticket,
                        user_id_alter = '$userid',
                        data_alteracao = '$today'
                        ";
                        $DB->query($hystori);
                     }
                  }
               }else if($acao == "alterar"){
                  foreach ($data as $key => $value) {
                     $hystori = "
                     UPDATE glpi_computer_lacre_hystori 
                     SET status = 3,
                     username = '$username',
                     user_id_alter = '$userid',
                     data_alteracao = '$today' ,
                     WHERE 
                     computer_id='".$computer_id."'
                     AND lacre_number = '".$value."'
                     AND status=1
                     AND id_ticket='".$id_ticket."'";    
                     $DB->query($hystori);

                     $hystori = "
                     INSERT INTO glpi_computer_lacre_hystori SET
                     computer_id = '".$computer_id."',
                     lacre_number = '".$value."',
                     status = 3,
                     username = '$username',
                     user_id_alter = '$userid',
                     id_ticket = $id_ticket,
                     data_alteracao = '$today'
                     ";
                     $DB->query($hystori);                                          
                  } 
               }
            }
         }
         Html::redirect("{$CFG_GLPI['root_doc']}/front/ticket.form.php?id=$id_ticket");
      }               
   }
?>
<script src="https://cdn.jsdelivr.net/jquery.validation/1.16.0/jquery.validate.min.js"></script>
<script type="text/javascript">
   
   $( ".acao_lacre" ).click(function() {
      
      if($(this).val() === 'validar'){
         $(this).parent().parent().find('.numero_lacre').attr('readonly', true);
         $(this).parent().parent().find('.numero_lacre').css('background-color', '#e0e0e0');
      }else{
         $(this).parent().parent().find('.numero_lacre').removeAttr('readonly');
         $(this).parent().parent().find('.numero_lacre').css('background-color', 'field');
      }
   });
   $( "#formLacre" ).validate({
      messages: {
         "numero_lacre[]": {
            required: "Este campo é obrigatório.",
            maxlength: "Digite não mais do que {0} caracteres.",
            minlength: "Por favor, insira pelo menos {0} caracteres."
         }
      }
   });
</script>



    





   
  

    

 

   

   




  
     
        
