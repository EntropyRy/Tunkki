/* stimulusFetch: 'lazy' */
import { Controller } from "@hotwired/stimulus";

const DEFAULT_TOKENS = [
  "streamplayer",
  "links",
  "dj_timetable",
  "vj_timetable",
  "dj_bio",
  "vj_bio",
  "rsvp",
  "stripe_ticket",
  "art_artist_list",
  "happening_list",
];

const DEFAULT_TOKEN_MAP = DEFAULT_TOKENS.reduce((map, token) => {
  map[token] = `<span class="markdown-token-badge" data-token="${token}">{{ ${token} }}</span>`;
  return map;
}, {});

export default class extends Controller {
  static values = {
    tokens: Array,
    tokenMap: Object,
    format: { type: String, default: 'simple' },
    headingLevels: Array,
    eventButtonFi: String,
    eventButtonEn: String,
  };

  async connect() {
    this.textarea =
      this.element instanceof HTMLTextAreaElement
        ? this.element
        : this.element.querySelector("textarea");
    this.syncing = false;
    this.tokenButtonAdded = false;
    this.headingCleanupScheduled = false;
    this.onToolbarClick = () => this.scheduleHeadingCleanup();

    if (!this.textarea) {
      return;
    }

    try {
      const editorMod = await import("@toast-ui/editor");
      const Editor =
        editorMod?.Editor ||
        editorMod?.default?.Editor ||
        editorMod?.default ||
        null;

      if (!Editor) {
        console.error("Toast UI Editor failed to load");
        return;
      }

      this.textarea.classList.add("markdown-editor--hidden");
      this.container = document.createElement("div");
      this.textarea.parentNode.insertBefore(this.container, this.textarea.nextSibling);

      this.editor = new Editor({
        el: this.container,
        height: "520px",
        initialEditType: "markdown",
        previewStyle: "vertical",
        usageStatistics: false,
        initialValue: this.textarea.value || "",
        toolbarItems: this.toolbarItems(),
        events: {
          change: () => {
            this.syncTextarea();
            this.renderPreview();
          },
        },
      });

      requestAnimationFrame(() => {
        this.renderPreview();
        this.scheduleHeadingCleanup();
      });
      if (!this.isSimple) {
        this.injectTokenButton();
      }
      this.injectEventButtons();
      this.container.addEventListener("click", this.onToolbarClick);
    } catch (error) {
      console.error("Failed to initialize Toast UI Editor", error);
      this.teardown();
    }
  }

  disconnect() {
    this.teardown();
  }

  toolbarItems() {
    switch (this.formatValue) {
      case 'telegram':
        // Only Telegram MarkdownV2 supported: *bold*, _italic_, ~strike~, [link](url)
        return [
          ["bold", "italic", "strike"],
          ["link"],
        ];
      case 'simple':
        return [
          ["heading", "bold", "italic", "strike"],
          ["quote", "link", "hr"],
          ["ul", "ol"],
        ];
      case 'event':
      default:
        return [
          ["heading", "bold", "italic", "strike"],
          ["quote", "link", "code", "codeblock"],
          ["ul", "ol", "task", "table", "hr"],
        ];
    }
  }

  injectTokenButton() {
    if (!this.container) return;

    const toolbar = this.container.querySelector(".toastui-editor-defaultUI-toolbar");
    if (!toolbar) return;

    if (this.tokenButtonAdded || toolbar.querySelector(".markdown-token-button")) {
      this.tokenButtonAdded = true;
      return;
    }

    const group = document.createElement("div");
    group.className = "toastui-editor-defaultUI-toolbar-group";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "toastui-editor-toolbar-icons markdown-token-button";
    button.setAttribute("aria-label", "Insert token");
    button.textContent = "{{}}";
    button.addEventListener("click", (event) => {
      event.preventDefault();
      this.promptAndInsertToken();
    });

    group.appendChild(button);
    toolbar.appendChild(group);
    this.tokenButtonAdded = true;
  }

  injectEventButtons() {
    if (!this.container || !this.hasEventButtonFiValue || !this.hasEventButtonEnValue) return;

    const toolbar = this.container.querySelector(".toastui-editor-defaultUI-toolbar");
    if (!toolbar) return;

    if (toolbar.querySelector(".markdown-event-button")) {
      return;
    }

    const group = document.createElement("div");
    group.className = "toastui-editor-defaultUI-toolbar-group";

    const buttonFi = document.createElement("button");
    buttonFi.type = "button";
    buttonFi.className = "toastui-editor-toolbar-icons markdown-event-button";
    buttonFi.setAttribute("aria-label", "Insert event button (FI)");
    buttonFi.textContent = "Event FI";
    buttonFi.addEventListener("click", (event) => {
      event.preventDefault();
      this.insertEventButton(this.eventButtonFiValue, "Siirry tapahtumaan");
    });

    const buttonEn = document.createElement("button");
    buttonEn.type = "button";
    buttonEn.className = "toastui-editor-toolbar-icons markdown-event-button";
    buttonEn.setAttribute("aria-label", "Insert event button (EN)");
    buttonEn.textContent = "Event EN";
    buttonEn.addEventListener("click", (event) => {
      event.preventDefault();
      this.insertEventButton(this.eventButtonEnValue, "Go to event");
    });

    group.appendChild(buttonFi);
    group.appendChild(buttonEn);
    toolbar.appendChild(group);
  }

  insertEventButton(url, label) {
    if (!this.editor || !url) return;

    const snippet = [
      '<div class="email-event-button-wrap">',
      `  <a class="email-event-button" href="${url}">${label}</a>`,
      '</div>',
      '',
    ].join("\n");

    this.editor.insertText(snippet);
    this.syncTextarea();
  }

  promptAndInsertToken() {
    const options = this.tokens;
    if (!options.length) return;

    const select = document.createElement("select");
    select.className = "form-control";

    options.forEach((token) => {
      const option = document.createElement("option");
      option.value = token;
      option.textContent = token;
      select.appendChild(option);
    });

    const wrapper = document.createElement("div");
    wrapper.className = "token-picker";
    const label = document.createElement("label");
    label.textContent = "Insert token";
    label.className = "form-label";
    wrapper.appendChild(label);
    wrapper.appendChild(select);

    const dialog = document.createElement("dialog");
    dialog.className = "token-picker-dialog";
    dialog.appendChild(wrapper);

    const actions = document.createElement("div");
    actions.className = "form-group text-right";

    const cancel = document.createElement("button");
    cancel.type = "button";
    cancel.textContent = "Cancel";
    cancel.className = "btn btn-default";
    cancel.addEventListener("click", () => dialog.close());

    const submit = document.createElement("button");
    submit.type = "button";
    submit.textContent = "Insert";
    submit.className = "btn btn-primary";
    submit.addEventListener("click", () => {
      this.insertToken(select.value);
      dialog.close();
    });

    actions.appendChild(cancel);
    actions.appendChild(submit);
    wrapper.appendChild(actions);

    dialog.addEventListener("close", () => dialog.remove());
    document.body.appendChild(dialog);
    dialog.showModal();
  }

  insertToken(token) {
    if (!this.editor) return;
    const safeToken = token.replace(/[^a-z0-9_]/gi, "");
    if (!safeToken) return;

    const text = `{{ ${safeToken} }}`;
    this.editor.insertText(text);
    this.syncTextarea();
  }

  decorateTokens(html) {
    const map = this.tokenMap;

    if (!Object.keys(map).length) {
      return html;
    }

    return html.replace(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi, (match, rawToken) => {
      const token = rawToken.toLowerCase();
      if (map[token]) {
        return map[token];
      }

      return `<span class="markdown-token-badge markdown-token-badge--unknown" data-token="${token}">${match}</span>`;
    });
  }

  syncTextarea() {
    if (this.textarea && this.editor) {
      const raw = this.editor.getMarkdown();
      // Strip backslashes in tokens before saving
      const normalized = this.normalizeTokens(raw);
      this.textarea.value = normalized;

      // If the editor content differs, update it to keep tokens unescaped in both modes
      if (!this.syncing && normalized !== raw) {
        this.syncing = true;
        this.editor.setMarkdown(normalized, false);
        this.syncing = false;
      }
    }
  }

  normalizeTokens(markdown) {
    return markdown.replace(/\{\{\s*([^}]+)\s*\}\}/g, (match, inner) => {
      const cleaned = inner
        // Remove any escaping before underscores (Toast UI adds these on mode switches)
        .replace(/\\+_/g, "_")
        // Strip any remaining escaping characters inside the token name
        .replace(/\\+/g, "")
        .trim();

      return `{{ ${cleaned} }}`;
    });
  }


  renderPreview() {
    if (!this.editor || !this.container) return;

    const previewEl = this.container.querySelector(".toastui-editor-md-preview .toastui-editor-contents");
    if (!previewEl) return;

    const decorated = this.decorateTokens(this.editor.getHTML());
    if (decorated !== previewEl.innerHTML) {
      previewEl.innerHTML = decorated;
    }
  }

  scheduleHeadingCleanup() {
    if (this.headingCleanupScheduled) {
      return;
    }

    this.headingCleanupScheduled = true;
    requestAnimationFrame(() => {
      this.headingCleanupScheduled = false;
      this.removeDisallowedHeadings();
    });
  }

  removeDisallowedHeadings() {
    if (!this.container) return;

    const allowed = this.allowedHeadingLevels;
    const items = this.container.querySelectorAll(
      "li[data-type='Heading'][data-level]",
    );

    items.forEach((item) => {
      const level = Number(item.getAttribute("data-level"));
      if (!allowed.includes(level)) {
        item.remove();
      }
    });
  }

  teardown() {
    if (this.editor && typeof this.editor.destroy === "function") {
      this.editor.destroy();
      this.editor = null;
    }
    if (this.container) {
      if (this.onToolbarClick) {
        this.container.removeEventListener("click", this.onToolbarClick);
      }
      this.container.remove();
      this.container = null;
    }
    if (this.textarea) {
      this.textarea.classList.remove("markdown-editor--hidden");
    }
  }

  get tokens() {
    if (this.isSimple) {
      return [];
    }

    return this.hasTokensValue && Array.isArray(this.tokensValue) && this.tokensValue.length
      ? this.tokensValue
      : DEFAULT_TOKENS;
  }

  get tokenMap() {
    if (this.isSimple) {
      return {};
    }

    return {
      ...DEFAULT_TOKEN_MAP,
      ...(this.hasTokenMapValue ? this.tokenMapValue : {}),
    };
  }

  get isSimple() {
    return this.formatValue === 'simple' || this.formatValue === 'telegram';
  }

  get allowedHeadingLevels() {
    if (this.hasHeadingLevelsValue && Array.isArray(this.headingLevelsValue)) {
      return this.headingLevelsValue
        .map((level) => Number(level))
        .filter((level) => Number.isInteger(level));
    }

    return [2, 3, 4, 5, 6];
  }
}
