$(document).ready(function(){

   $("#login").click(function() { getinfo(); });

});

function changecontent(name,desc,count)
{
   if (count==0)
      {
      $('#standname').html(name+' <span class="label label-danger" id="standcount">'+count+'</span>');
      }
   else
      {
      $('#standname').html(name+' <span class="label label-success" id="standcount">'+count+'</span>');
      }
   $('#standinfo').html(desc);
   showbuttons(count);
}

function showbuttons(count)
{
   if (count==0)
      {
      $('#standactions').hide();
      }
   else
      {
      $('#standactions').show();
      }
}

function getinfo()
{
   sms_uuid='xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {var r = Math.random()*16|0,v=c=='x'?r:r&0x3|0x8;return v.toString(16);});
   $.ajax({
   url: "receive.php?sms_text=FREE&sender=421905474209&receive_time="+new Date()+"&sms_uuid="+sms_uuid
   }).done(function(html) {
   $( "#console" ).html(html);
   });
}