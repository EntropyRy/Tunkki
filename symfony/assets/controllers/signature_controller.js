import SignaturePad from 'signature_pad';
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static pad;
    static targets = ['signature', 'canvas', 'saveBtn', 'clearBtn'];
    connect() {
        if (this.hasCanvasTarget){
            this.pad = new SignaturePad(this.canvasTarget);
        }
        console.log(this.hasCanvasTarget);
    }
    saveCanvas() {
        if (this.pad.isEmpty()){
            alert('Empty signature');
        } else {
            this.saveBtnTarget.style.display = "none";
            this.clearBtnTarget.style.display = "";
            this.canvasTarget.style.display = "none";
            let dataUrl = this.pad.toDataURL('image/png');
            let img = document.createElement("img");
            img.setAttribute('src', dataUrl);
            this.signatureTarget.append(img);
            document.getElementById("booking_consent_renterSignature").value = dataUrl;
            document.getElementById("booking_consent_Agree").removeAttribute('disabled');
        }
    }
    clearCanvas() {
        this.clearBtnTarget.style.display = "none";
        this.signatureTarget.textContent = '';
        this.saveBtnTarget.style.display = '';
        this.pad.clear();
        document.getElementById("booking_consent_Agree").setAttribute('disabled', 'disabled');
        this.canvasTarget.style.display = '';
    }
}
