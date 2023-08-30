import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
  static values = {
    url: String,
    title: String,
  };
  connect() {
    this.url = document.location.href;
    this.title = document.title;
  }
  shareUrl(event) {
    if (navigator.share) {
      navigator
        .share({
          title: this.title,
          url: this.url,
        })
        .then(() => {
          console.log("Thanks for sharing!");
        })
        .catch(console.error);
    } else {
      this.copyToClipboard(event);
    }
  }
  copyToClipboard(event) {
    var copyTest = document.queryCommandSupported("copy");
    var elOriginalText = this.title;
    var clip = navigator.clipboard;
    var text = this.url;
    console.log(event.currentTarget);

    if (clip) {
      try {
        let successful = clip.writeText(text);
        var msg = successful
          ? "The URL has been copied to your clipboard"
          : "Whoops, not copied!";
        event.currentTarget.innerText = msg;
        console.log("Page URL copied to clipboard with Cliboard API");
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
        var msg = successful
          ? "The URL has been copied to your clipboard"
          : "Whoops, not copied!";
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
