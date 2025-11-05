import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('scroll-to-planner', this.scrollToPlanner.bind(this));
    }

    scrollToPlanner(event) {
        const target = document.getElementById('nakkikone-planner');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}
