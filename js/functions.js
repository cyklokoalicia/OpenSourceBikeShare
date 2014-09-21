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
      $('#rent').addClass('disabled');
      }
   else
      {
      $('#rent').removeClass('disabled');
      }
}