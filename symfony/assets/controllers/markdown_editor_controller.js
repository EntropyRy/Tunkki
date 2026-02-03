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
        customHTMLRenderer: this.customHTMLRenderer(),
        events: {
          change: () => {
            this.syncTextarea();
          },
          beforeConvertWysiwygToMarkdown: (markdown) =>
            this.replaceTokenHtmlWithPlaceholder(markdown),
        },
      });

      requestAnimationFrame(() => {
        this.scheduleHeadingCleanup();
      });
      this.container.addEventListener("click", this.onToolbarClick);
      this.registerCommands();
    } catch (error) {
      console.error("Failed to initialize Toast UI Editor", error);
      this.teardown();
    }
  }

  disconnect() {
    this.teardown();
  }

  toolbarItems() {
    const htmlToMarkdownItem = this.buildHtmlToMarkdownToolbarItem();
    const tokenItem = this.buildTokenToolbarItem();
    const eventButtons = this.buildEventToolbarItems();

    switch (this.formatValue) {
      case 'telegram':
        // Only Telegram MarkdownV2 supported: *bold*, _italic_, ~strike~, [link](url)
        return [
          ["bold", "italic", "strike"],
          ["link", htmlToMarkdownItem],
        ];
      case 'simple':
        return [
          ["heading", "bold", "italic", "strike"],
          ["quote", "link", "hr"],
          ["ul", "ol", htmlToMarkdownItem],
        ];
      case 'event':
      default:
        return [
          ["heading", "bold", "italic", "strike", tokenItem],
          ["quote", "link", "code", "codeblock", ...eventButtons],
          ["ul", "ol", "task", "table", "hr", htmlToMarkdownItem],
        ];
    }
  }

  buildTokenToolbarItem() {
    return {
      name: "insertToken",
      tooltip: "Insert token",
      className: "markdown-token-button",
      text: "{{}}",
      command: "insertToken",
    };
  }

  buildEventToolbarItems() {
    if (!this.hasEventButtonFiValue || !this.hasEventButtonEnValue) {
      return [];
    }

    return [
      {
        name: "insertEventFi",
        tooltip: "Insert event button (FI)",
        className: "markdown-event-button",
        text: "Event FI",
        command: "insertEventFi",
      },
      {
        name: "insertEventEn",
        tooltip: "Insert event button (EN)",
        className: "markdown-event-button",
        text: "Event EN",
        command: "insertEventEn",
      },
    ];
  }

  buildHtmlToMarkdownToolbarItem() {
    return {
      name: "htmlToMarkdown",
      tooltip: "Convert HTML to Markdown",
      className: "markdown-html-to-md-button",
      text: "HTML→MD",
      command: "htmlToMarkdown",
    };
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

  convertHtmlToMarkdown() {
    if (!this.editor || !this.textarea) return;

    const raw = this.textarea.value || "";
    if (!this.containsHtml(raw)) {
      return;
    }

    this.editor.setHTML(raw);
    const markdown = this.editor.getMarkdown();
    this.editor.setMarkdown(markdown, false);
    this.syncTextarea();
  }

  containsHtml(value) {
    return /<[a-z][\s\S]*>/i.test(value);
  }

  registerCommands() {
    if (!this.editor) return;

    this.editor.addCommand("markdown", "insertToken", () => {
      this.promptAndInsertToken();
      return true;
    });

    this.editor.addCommand("wysiwyg", "insertToken", () => {
      this.promptAndInsertToken();
      return true;
    });

    this.editor.addCommand("markdown", "htmlToMarkdown", () => {
      this.convertHtmlToMarkdown();
      return true;
    });

    this.editor.addCommand("wysiwyg", "htmlToMarkdown", () => {
      this.convertHtmlToMarkdown();
      return true;
    });

    if (this.hasEventButtonFiValue) {
      this.editor.addCommand("markdown", "insertEventFi", () => {
        this.insertEventButton(this.eventButtonFiValue, "Siirry tapahtumaan");
        return true;
      });
      this.editor.addCommand("wysiwyg", "insertEventFi", () => {
        this.insertEventButton(this.eventButtonFiValue, "Siirry tapahtumaan");
        return true;
      });
    }

    if (this.hasEventButtonEnValue) {
      this.editor.addCommand("markdown", "insertEventEn", () => {
        this.insertEventButton(this.eventButtonEnValue, "Go to event");
        return true;
      });
      this.editor.addCommand("wysiwyg", "insertEventEn", () => {
        this.insertEventButton(this.eventButtonEnValue, "Go to event");
        return true;
      });
    }
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

  syncTextarea() {
    if (this.textarea && this.editor) {
      const raw = this.replaceTokenHtmlWithPlaceholder(this.editor.getMarkdown());
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

  customHTMLRenderer() {
    if (this.isSimple) {
      return {};
    }

    return {
      text: (node) => {
        const literal = node.literal || "";
        const parts = this.splitTokenText(literal);
        if (parts.length === 1 && parts[0].type === "text") {
          return parts[0];
        }
        return parts;
      },
    };
  }

  splitTokenText(text) {
    const tokens = [];
    const regex = /\{\{\s*([a-z0-9_]+)\s*\}\}/gi;
    let cursor = 0;
    let match;

    while ((match = regex.exec(text)) !== null) {
      if (match.index > cursor) {
        tokens.push({ type: "text", content: text.slice(cursor, match.index) });
      }
      tokens.push({ type: "html", content: this.buildTokenHtml(match[1]) });
      cursor = match.index + match[0].length;
    }

    if (cursor < text.length) {
      tokens.push({ type: "text", content: text.slice(cursor) });
    }

    return tokens.length ? tokens : [{ type: "text", content: text }];
  }

  buildTokenHtml(token) {
    const key = token.toLowerCase();
    const html = this.tokenMap[key];

    if (html) {
      return html;
    }

    return `<span class="markdown-token-badge markdown-token-badge--unknown" data-token="${key}">{{ ${key} }}</span>`;
  }

  replaceTokenHtmlWithPlaceholder(markdown) {
    if (!markdown) return markdown;

    return markdown.replace(
      /<[^>]*data-token="([^"]+)"[^>]*>[\s\S]*?<\/[^>]+>/g,
      (_match, token) => `{{ ${token} }}`,
    );
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
