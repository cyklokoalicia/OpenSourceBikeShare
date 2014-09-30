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

function getsmscode(event)
{
   event.preventDefault();
   $.ajax({
   url: "command.php?action=smscode&number="+$('#number').val()
   }).done(function(jsonresponse) {
   jsonobject=$.parseJSON(jsonresponse);
   if (jsonobject.error==1)
      {
      $('#console').html('<div class="alert alert-danger" role="alert">'+jsonobject.content+'</div>');
      }
   else
      {
      $('#console').html('');
      $("#validatednumber").val(jsonobject.content);
      $("#checkcode").val(jsonobject.checkcode);
      $("#step1").fadeOut();
      }
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
   $("#step2").fadeOut();
   if (jsonobject.error==1)
      {
      $('#console').html('<div class="alert alert-danger" role="alert">'+jsonobject.content+'</div>');
      }
   else $('#console').html('<div class="alert alert-success" role="alert">'+jsonobject.content+'</div>');
   });
}