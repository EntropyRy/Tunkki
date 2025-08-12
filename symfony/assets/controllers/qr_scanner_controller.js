/* stimulusFetch: 'lazy' */
import { Controller } from "@hotwired/stimulus";
import { Html5QrcodeScanner } from "html5-qrcode";

export default class extends Controller {
  static qrCodeScanner;
  static targets = [
    "video",
    "result",
    "email",
    "status",
    "referenceNumber",
    "given",
    "button",
  ];
  static values = { eventId: Number };

  connect() {
    this.setupQRCodeScanner();
  }

  setupQRCodeScanner() {
    let width = (this.videoTarget.offsetWidth / 5) * 3;
    this.qrCodeScanner = new Html5QrcodeScanner("video", {
      fps: 10,
      qrbox: { width: width, height: width },
    });
    this.qrCodeScanner.render(
      (qrCodeMessage) => {
        this.fetchTicket(qrCodeMessage);
      },
      (errorMessage) => {
        //console.error(errorMessage);
      },
    );
  }

  disconnect() {
    // Stop the QR code scanner when the controller disconnects
    this.qrCodeScanner?.stop();
  }

  fetchTicket(text) {
    fetch("/api/ticket/" + this.eventIdValue + "/" + text + "/info")
      .then((response) => response.json())
      .then((data) => this.showTicketStatus(JSON.parse(data)));
  }

  showTicketStatus(data) {
    this.statusTarget.classList.remove("text-danger");
    let middle =
      -(
        this.videoTarget.offsetHeight / 2 +
        this.resultTarget.offsetHeight / 2
      ) + "px";
    this.resultTarget.style.top = middle;
    this.referenceNumberTarget.innerText = data["referenceNumber"];
    this.emailTarget.innerText = data["email"];
    if (data["status"] == "paid") {
      this.statusTarget.innerText = "Paid";
    } else {
      this.statusTarget.innerText = "Not Paid";
      this.statusTarget.classList.add("text-danger");
    }
    // this.givenTarget.innerText = data["given"];
    if (!data["given"] && data["status"] == "paid") {
      this.buttonTarget.classList.remove("disabled");
      this.buttonTarget.classList.remove("d-none");
    } else {
      let result = [];
      result["ok"] = "Ticket Already Given Out";
      this.hideTicketStatus(result);
    }
  }

  giveTicket() {
    let text = this.referenceNumberTarget.innerText;
    fetch("/api/ticket/" + this.eventIdValue + "/" + text + "/give")
      .then((response) => response.json())
      .then((data) => this.hideTicketStatus(JSON.parse(data)));
  }

  hideTicketStatus(data) {
    if ("error" in data) {
      this.givenTarget.innerText = "Check error";
      this.statusTarget.innerText = data["error"];
    } else {
      this.givenTarget.innerText = "Ticket Given Out";
    }
    setTimeout(() => {
      this.resultTarget.style.top = "0px";
      this.referenceNumberTarget.innerText = "";
      this.emailTarget.innerText = "";
      this.statusTarget.innerText = "";
      this.givenTarget.innerText = "";
    }, 3000);
    this.buttonTarget.classList.add("d-none");
    this.buttonTarget.classList.add("disabled");
  }
}
