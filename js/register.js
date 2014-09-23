$(document).ready(function(){


   $('#step2').bootstrapValidator({
        feedbackIcons: {
            valid: 'glyphicon glyphicon-ok',
            invalid: 'glyphicon glyphicon-remove',
            validating: 'glyphicon glyphicon-refresh'
        },
        fields: {
            smscode: {
                validators: {
                    callback: {
                        message: 'SMS code is in AB 123456 format.',
                        callback: function(value, validator) {
                                  smscode=$("#smscode").val();
                                  smscode=smscode.replace(/ /g,"");
                                  if (smscode.search('[a-zA-Z]{2}[0-9]{6}')==0) return true;
                                  else return false;
                                  } },
                    notEmpty: {
                        message: 'Please, enter SMS code received to your phone.'
                    }
                }
            },
            fullname: {
                validators: {
                    notEmpty: {
                        message: 'Please, enter your firstname and lastname.'
                    }
                }
            },
            email: {
                validators: {
                    emailAddress: {
                        message: 'Email address is incorrect.'
                    },
                    notEmpty: {
                        message: 'Please, enter your email.'
                    }
                }
            },
            password: {
                validators: {
                    identical: {
                        field: 'password2',
                        message: 'Passwords do not match.'
                    },
                    notEmpty: {
                        message: 'Please, enter your password.'
                    }
                }
            },
            password2: {
                validators: {
                    identical: {
                        field: 'password',
                        message: 'Passwords do not match.'
                    },
                    notEmpty: {
                        message: 'Please, confirm your password.'
                    }
                }
            }
        }
    });

   $("#step1").submit(function(event) { getsmscode(event); });
   $("#step2").submit(function(event) { register(event); });

});

function changecontent(name,desc,count)
{
   if (count==0)
      {
      $('#standname').html(name+' <span class="label label-danger" id="standcount">No bicycles</span>');
      }
   else if (count==1)
      {
      $('#standname').html(name+' <span class="label label-success" id="standcount">'+count+' bicycle</span>');
      }
   else
      {
      $('#standname').html(name+' <span class="label label-success" id="standcount">'+count+' bicycles</span>');
      }
   $('#standinfo').html(desc+' bicycles here: ');
   showbuttons(count);
}

function getsmscode(event)
{
   event.preventDefault();
   $( "#validate" ).prop( "disabled", true );
   $.ajax({
   url: "command.php?action=smscode&number="+$('#number').val()
   }).done(function(jsonresponse) {
   jsonobject=$.parseJSON(jsonresponse);
   $("#validatednumber").val(jsonobject.content);
   $("#checkcode").val(jsonobject.checkcode);
   $( "#step1" ).fadeOut();
   });
}

function register(event)
{
   event.preventDefault();
   $( "#register" ).prop( "disabled", true );
   $.ajax({
   url: "command.php?action=register&validatednumber="+$('#validatednumber').val()+"&checkcode="+$('#checkcode').val()+"&smscode="+$('#smscode').val()+"&fullname="+$('#fullname').val()+"&email="+$('#email').val()+"&password="+$('#password').val()+"&password2="+$('#password2').val()
   }).done(function(jsonresponse) {
   jsonobject=$.parseJSON(jsonresponse);
   $("#validatednumber").val(jsonobject.content);
   $("#checkcode").val(jsonobject.checkcode);
   $( "#step1" ).fadeOut();
   });
}