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
                        message: _sms_code,
                        callback: function(value, validator) {
                                  smscode=$("#smscode").val();
                                  smscode=smscode.replace(/ /g,"");
                                  if (smscode.search('[a-zA-Z]{2}[0-9]{6}')==0) return true;
                                  else return false;
                                  } },
                    notEmpty: {
                        message: _enter_sms_code
                    }
                }
            },
            fullname: {
                validators: {
                    notEmpty: {
                        message: _enter_names
                    }
                }
            },
            email: {
                validators: {
                    emailAddress: {
                        message: _email_incorrect
                    },
                    notEmpty: {
                        message: _enter_email
                    }
                }
            },
            password: {
                validators: {
                    identical: {
                        field: 'password2',
                        message: _passwords_nomatch
                    },
                    notEmpty: {
                        message: _enter_password
                    }
                }
            },
            password2: {
                validators: {
                    identical: {
                        field: 'password',
                        message: _passwords_nomatch
                    },
                    notEmpty: {
                        message: _enter_password
                    }
                }
            }
        }
    }).on('success.form.bv',function(e){ e.preventDefault(); });

   $("#step1").submit(function(e) { e.preventDefault(); getsmscode(); });
   $("#step2").submit(function(e) { e.preventDefault(); register(); });

});

function getsmscode()
{
   $.ajax({
   url: "command.php?action=smscode&number="+$('#number').val()
   }).done(function(jsonresponse) {
   jsonobject=$.parseJSON(jsonresponse);
   $('#console').html('');
   $("#validatednumber").val(jsonobject.content);
   $("#checkcode").val(jsonobject.checkcode);
   $("#existing").val(jsonobject.existing);
   $("#step1").fadeOut();
   if (jsonobject.existing==1)
      {
      $('h1').html(_existing_user).fadeIn();
      $('#step2title').html(_step2).fadeIn();
      $('#register').html(_set_password);
      $('#regonly').fadeOut();
      }
   });
}

function register()
{
   $("#console").fadeOut();
   $("#register").prop("disabled", true);
   $.ajax({
   url: "command.php?action=register&validatednumber="+$('#validatednumber').val()+"&checkcode="+$('#checkcode').val()+"&smscode="+$('#smscode').val()+"&fullname="+$('#fullname').val()+"&email="+$('#email').val()+"&password="+$('#password').val()+"&password2="+$('#password2').val()+"&existing="+$('#existing').val()
   }).done(function(jsonresponse) {
   jsonobject=$.parseJSON(jsonresponse);
   if (jsonobject.error==1)
      {
      $('#console').html('<div class="alert alert-danger" role="alert">'+jsonobject.content+'</div>');
      $("#console").fadeIn();
      $("#register").prop("disabled", false );
      }
   else
      {
      $("#step2").fadeOut();
      $("#console").fadeIn();
      $('#console').html('<div class="alert alert-success" role="alert">'+jsonobject.content+'</div>');
      }
   });
}