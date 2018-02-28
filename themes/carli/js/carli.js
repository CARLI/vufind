function getURLParameter(name) {
    return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search) || [null, ''])[1].replace(/\+/g, '%20')) || null;
}

function autoLoginCarli() {
    forceMethod = getURLParameter('auth_method');
    // allow override
    if (forceMethod) {
        return;
    }
    if(document.getElementById('carli-login-link')!=null||document.getElementById('carli-login-link')!=""){ 
        hideLoginPageCarli();
        document.getElementById('carli-login-link').click();
    }
}

function hideLoginPageCarli() {
    if(document.body!=null||document.body!=""){ 
        document.body.style.display = 'none';
    }
}

function switchCatalog(choiceObj) {
    var theaction = choiceObj.form.action;
    var thiscatalog = choiceObj.selectedIndex;

    // set it to All I-Share, /all/vf-xxx
    if (thiscatalog == 0) {
       if (theaction.search(/\/all/) < 0) {
          choiceObj.form.action = theaction.replace("/vf-", "/all/vf-");
       }
    // set it to local catalog, /vf-xxx
    } else {
       if (theaction.search(/\/all/) >= 0) {
          choiceObj.form.action = theaction.replace("/all/vf-", "/vf-");
       }
    }
}
