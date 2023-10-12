import { Controller } from "@hotwired/stimulus";

stimulusFetch: "lazy";

export default class extends Controller {
  static targets = ["input"];
  connect() {
    const input = document.getElementById("nameSearchInput");
    const posts = document.getElementsByClassName("post");

    input.addEventListener(
      "keyup",
      this.delay(function (e) {
        //console.log(posts);
        name = this.value;
        for (var i = 0; i < posts.length; i++) {
          if (
            posts[i].dataset.name.toLowerCase().indexOf(name.toLowerCase()) >
              -1 ||
            name == ""
          ) {
            posts[i].classList.add("show");
          } else {
            posts[i].classList.remove("show");
          }
        }
      }, 500),
    );
  }
  delay(fn, ms) {
    let timer = 0;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(fn.bind(this, ...args), ms || 0);
    };
  }
}
