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
