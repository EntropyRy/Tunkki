import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input']
    connect() {
        //this.element.textContent = 'Hello Stimulus! Edit me in assets/controllers/hello_controller.js';
    }
    copyClass({params: {iclass} }) {
        this.inputTarget.value = iclass;
    }
}
