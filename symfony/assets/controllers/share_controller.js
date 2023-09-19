import { trans, SHARE_COPIED } from "../translator";
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static values = {
    url: String,
    title: String,
  };
  connect() {
    if (!this.urlValue && !this.titleValue) {
      this.urlValue = document.location.href;
      this.titleValue = document.title;
    }
  }
  shareUrl(event) {
    if (navigator.share && !this.isMacintosh()) {
      navigator
        .share({
          title: this.titleValue,
          url: this.urlValue,
        })
        .then(() => {
          // console.log("Thanks for sharing!");
        })
        .catch(console.error);
    } else {
      this.copyToClipboard(event);
    }
  }
  isMacintosh() {
    return navigator.platform.indexOf("Mac") > -1;
  }
  copyToClipboard(event) {
    var copyTest = document.queryCommandSupported("copy");
    var elOriginalText = this.titleValue;
    var clip = navigator.clipboard;
    var text = this.urlValue;
    //console.log(event.currentTarget);

    if (clip) {
      try {
        let successful = clip.writeText(text);
        var msg = successful ? trans(SHARE_COPIED) : "Whoops, not copied!";
        event.currentTarget.innerText = msg;
        //console.log("Page URL copied to clipboard with Cliboard API");
      } catch (err) {
        console.error("Failed to copy: ", err);
      }
    } else if (copyTest === true) {
      var copyTextArea = document.createElement("textarea");
      copyTextArea.value = text;
      document.body.appendChild(copyTextArea);
      copyTextArea.select();
      try {
        var successful = document.execCommand("copy");
        var msg = successful ? trans(SHARE_COPIED) : "Whoops, not copied!";
        event.currentTarget.innerText = msg;
      } catch (err) {
        console.log("Oops, unable to copy");
      }
      document.body.removeChild(copyTextArea);
    } else {
      // Fallback if browser doesn't support .execCommand('copy')
      window.prompt("Copy to clipboard: Ctrl+C or Command+C, Enter", text);
    }
  }
}
