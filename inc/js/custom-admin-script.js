
jQuery( function ( $ ) {
    const apiSendButton = document.getElementById('apiSendButton');
    const domainServiceSendButton = document.getElementById('serviceDomainSave');
    const form = document.getElementById('cdntr-apiform');
    const errorMessageDiv = document.createElement('div');

    errorMessageDiv.id = 'error-message';
    errorMessageDiv.style.color = 'red';
    form.prepend(errorMessageDiv);

    apiSendButton.addEventListener('click',function (event){
        send_form (event)
    });
    if (domainServiceSendButton){
        domainServiceSendButton.addEventListener('click',function (event){
            send_form (event)
        });
    }

    $('#show-hide-password').on('click', function() {
        var passwordInput = $('#cdntr_api_password');
        var passwordFieldType = passwordInput.attr('type');

        if (passwordFieldType === 'password') {
            passwordInput.attr('type', 'text');
            $('#password-text').text('Hide');
        } else {
            passwordInput.attr('type', 'password');
            $('#password-text').text('Show');
        }
    });
    function addHiddenInputIfNotExist(form) {
        if (!document.getElementById('validate_config_hidden')) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'cdntr[validate_config]';
            hiddenInput.value = '1';
            form.appendChild(hiddenInput);
        }
    }

    function send_form(event){
        const apiUser = document.getElementById('cdntr_api_user').value.trim();
        const apiPassword = document.getElementById('cdntr_api_password').value.trim();
        event.preventDefault();
        if ($(apiSendButton).attr('name') === 'cdntr[validate_config]') {
            addHiddenInputIfNotExist(form);
        }
        errorMessageDiv.innerHTML = '';



        if (apiPassword === '' && apiUser === ''){
            document.getElementById('cdn_hostname_el').value = '';
            form.submit();
        }else if ((apiUser !== '' && apiPassword === '') || (apiPassword !== '' && apiUser === '')) {
            errorMessageDiv.innerHTML = `
                    <div class="notice notice-error is-dismissible">
                        <p><strong>${wp.i18n.__('Purge All CDNTR Cache failed:', 'cdntr')}</strong> ${wp.i18n.__('API User ve API Password alanlarını eksiksiz giriniz.', 'cdntr')}</p>
                    </div>
                `;
            return;
        }else{
            const externalServiceUrl = 'https://cdn.com.tr/api/checkAccount';
            const auth = btoa(apiUser + ':' + apiPassword);
            console.log('auth',auth);
            $.ajax({
                url: externalServiceUrl,
                type: 'POST',
                headers: {
                    'Authorization': 'Basic ' + auth,
                    'Content-Type': 'application/json'
                },
                success: function (response) {
                    if (response) {
                        var cdnHostnamesJson = JSON.stringify(response.cdn_hostnames);
                        console.log('JSONDATA',cdnHostnamesJson)

                        // Input alanlarına değer atama
                        document.getElementById('cdntr_hostname_arr').value = cdnHostnamesJson;
                        console.log('ınptval', document.getElementById('cdntr_hostname_arr').value)
                        document.getElementById('cdntr_account_expires').value = response.expire_at;
                        document.getElementById('cdntr_is_purge_all_button').value = 1;
                        form.submit();
                        console.log('JQResponse',response)
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('Error fetching data:', textStatus, errorThrown);
                    const errorMessage = `
                        <div class="notice notice-error is-dismissible">
                            <p><strong>${wp.i18n.__('CDNTR API failed:', 'cdntr')}</strong> ${wp.i18n.__('API User ve API Password bilgileri hatalı.', 'cdntr')}</p>
                         </div>
                        `;
                    document.getElementById('cdntr_is_purge_all_button').value = 0;

                    errorMessageDiv.innerHTML = errorMessage;
                }
            });
        }
    }


});
