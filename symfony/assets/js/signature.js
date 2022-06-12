import SignaturePad from 'signature_pad';

$(document).ready(function() {
    const canvas = document.querySelector("canvas");
    if (canvas){
        const signaturePad = new SignaturePad(canvas);
        $('.clearCanvas').click(function() {
            $(this).hide();
            $('.saveCanvas').show();
            $('#signature').animate({height: "0px"}, 400, function() {
                $('#signature').empty();
                signaturePad.clear();
                $('#signature').animate({height: "250px"});
                $('#booking_consent_Agree').prop('disabled', true);
                $('.canvas').show();
            });
        });
        $('.saveCanvas').click(function() {
            if (signaturePad.isEmpty()){
                alert('Empty signature');
            } else {
                $('.saveCanvas').hide();
                $('.clearCanvas').show();
                $('.canvas').hide();
                $('#signature').empty();
                let dataUrl = signaturePad.toDataURL('image/png');
                let img = $('<img>').attr('src', dataUrl);
                $('#signature').append(img);
                $('#signature').animate({height: "250px"});
                $('#booking_consent_renterSignature').val(dataUrl);
                $('#booking_consent_Agree').prop('disabled', false);
            }
        });
    }
});

