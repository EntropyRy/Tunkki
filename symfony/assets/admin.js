// Admin entrypoint: boots a minimal Stimulus app and pulls in admin-only styles/assets.
import { app } from "./admin_bootstrap.js";
import "@toast-ui/editor/dist/toastui-editor.css";
import "prosemirror-view/style/prosemirror.min.css";
import "./styles/admin/markdown-editor.css";
import "./styles/admin.css";
import MarkdownEditorController from "./controllers/markdown_editor_controller.js";
import "./js/admin_effects.js";

app.register("markdown-editor", MarkdownEditorController);
