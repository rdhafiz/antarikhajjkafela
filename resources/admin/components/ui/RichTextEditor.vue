<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    Undo2,
} from 'lucide-vue-next';

/**
 * Rich text editor (headings, emphasis, lists, quotes, links). The server
 * sanitizes the HTML again before saving.
 */
const model = defineModel<string | null>({ default: null });
const props = defineProps<{ invalid?: boolean; dir?: string; lang?: string; minHeight?: string }>();

const editor = useEditor({
    content: model.value ?? '',
    extensions: [
        StarterKit.configure({ heading: { levels: [2, 3] }, link: false }),
        Link.configure({ openOnClick: false, autolink: true, defaultProtocol: 'https', HTMLAttributes: { rel: 'noopener', target: null } }),
    ],
    editorProps: {
        attributes: {
            class: 'rte-content',
            ...(props.dir ? { dir: props.dir } : {}),
            ...(props.lang ? { lang: props.lang } : {}),
        },
    },
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        model.value = html === '<p></p>' ? null : html;
    },
});

watch(model, (value) => {
    if (editor.value && value !== editor.value.getHTML() && !(value === null && editor.value.isEmpty)) {
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
    }
});

function setLink(): void {
    const current = editor.value?.getAttributes('link').href as string | undefined;
    const url = window.prompt('Link address (https://…, /page, mailto: or tel:)', current ?? 'https://');

    if (url === null) return;
    if (url === '' || url === 'https://') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    if (!/^(https?:\/\/|\/|mailto:|tel:|#)/i.test(url)) return;

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

onBeforeUnmount(() => editor.value?.destroy());

const tools = [
    { icon: Bold, label: 'Bold', run: () => editor.value?.chain().focus().toggleBold().run(), active: () => editor.value?.isActive('bold') },
    { icon: Italic, label: 'Italic', run: () => editor.value?.chain().focus().toggleItalic().run(), active: () => editor.value?.isActive('italic') },
    { icon: Heading2, label: 'Heading', run: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), active: () => editor.value?.isActive('heading', { level: 2 }) },
    { icon: Heading3, label: 'Subheading', run: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(), active: () => editor.value?.isActive('heading', { level: 3 }) },
    { icon: List, label: 'Bulleted list', run: () => editor.value?.chain().focus().toggleBulletList().run(), active: () => editor.value?.isActive('bulletList') },
    { icon: ListOrdered, label: 'Numbered list', run: () => editor.value?.chain().focus().toggleOrderedList().run(), active: () => editor.value?.isActive('orderedList') },
    { icon: Quote, label: 'Quote', run: () => editor.value?.chain().focus().toggleBlockquote().run(), active: () => editor.value?.isActive('blockquote') },
    { icon: Minus, label: 'Divider', run: () => editor.value?.chain().focus().setHorizontalRule().run(), active: () => false },
    { icon: Link2, label: 'Link', run: setLink, active: () => editor.value?.isActive('link') },
];
</script>

<template>
    <div class="overflow-hidden rounded-lg border bg-white transition-[border-color,box-shadow] focus-within:border-brand focus-within:ring-3 focus-within:ring-brand/12" :class="invalid ? 'border-red-400' : 'border-line'">
        <div class="flex flex-wrap items-center gap-0.5 border-b border-line bg-canvas/60 px-1.5 py-1" role="toolbar" aria-label="Formatting">
            <button
                v-for="tool in tools"
                :key="tool.label"
                type="button"
                class="flex size-8 items-center justify-center rounded-md transition-colors"
                :class="tool.active() ? 'bg-brand text-white' : 'text-ink-soft hover:bg-white hover:text-ink'"
                :title="tool.label"
                :aria-label="tool.label"
                :aria-pressed="!!tool.active()"
                @click="tool.run()"
            >
                <component :is="tool.icon" class="size-4" />
            </button>
            <span class="mx-1 h-5 w-px bg-line" />
            <button type="button" class="flex size-8 items-center justify-center rounded-md text-ink-soft hover:bg-white hover:text-ink" title="Undo" aria-label="Undo" @click="editor?.chain().focus().undo().run()">
                <Undo2 class="size-4" />
            </button>
            <button type="button" class="flex size-8 items-center justify-center rounded-md text-ink-soft hover:bg-white hover:text-ink" title="Redo" aria-label="Redo" @click="editor?.chain().focus().redo().run()">
                <Redo2 class="size-4" />
            </button>
        </div>
        <EditorContent :editor="editor" class="max-h-[420px] overflow-y-auto" :style="{ minHeight: minHeight ?? '140px' }" />
    </div>
</template>

<style>
.rte-content {
    padding: 0.75rem 0.9rem;
    min-height: inherit;
    font-size: 0.875rem;
    line-height: 1.65;
    color: var(--color-ink);
    outline: none;
}
.rte-content > * + * { margin-top: 0.6em; }
.rte-content h2 { font-size: 1.15rem; font-weight: 700; }
.rte-content h3 { font-size: 1rem; font-weight: 700; }
.rte-content ul { list-style: disc; padding-left: 1.25rem; }
.rte-content ol { list-style: decimal; padding-left: 1.25rem; }
.rte-content blockquote { border-left: 3px solid var(--admin-secondary); padding-left: 0.8rem; color: var(--color-ink-soft); }
.rte-content a { color: var(--admin-primary); text-decoration: underline; }
.rte-content hr { border-color: var(--color-line); }
.rte-content p.is-editor-empty:first-child::before { content: 'Start typing…'; color: var(--color-ink-muted); float: left; height: 0; pointer-events: none; }
</style>
