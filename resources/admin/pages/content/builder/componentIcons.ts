import type { Component } from 'vue';
import {
    Award,
    BadgeDollarSign,
    ChartNoAxesColumn,
    CircleHelp,
    CirclePlay,
    ClipboardList,
    Columns2,
    Images,
    Info,
    LayoutGrid,
    List,
    ListOrdered,
    Mail,
    MapPinned,
    Megaphone,
    MessageSquareQuote,
    Newspaper,
    PanelTop,
    Quote,
    RectangleHorizontal,
    Square,
    Text,
    Timer,
    Users,
} from 'lucide-vue-next';

/** Admin icons for page-builder components (by the icon name the server sends). */
const icons: Record<string, Component> = {
    'panel-top': PanelTop,
    'rectangle-horizontal': RectangleHorizontal,
    info: Info,
    'columns-2': Columns2,
    text: Text,
    'layout-grid': LayoutGrid,
    'chart-no-axes-column': ChartNoAxesColumn,
    'list-ordered': ListOrdered,
    quote: Quote,
    users: Users,
    'circle-help': CircleHelp,
    'circle-play': CirclePlay,
    award: Award,
    'badge-dollar-sign': BadgeDollarSign,
    megaphone: Megaphone,
    'map-pinned': MapPinned,
    mail: Mail,
    'clipboard-list': ClipboardList,
    timer: Timer,
    list: List,
    'message-square-quote': MessageSquareQuote,
    images: Images,
    newspaper: Newspaper,
};

export function componentIcon(name: string): Component {
    return icons[name] ?? Square;
}

export const categoryLabels: Record<string, string> = {
    hero: 'Page tops',
    content: 'Content',
    media: 'Media',
    conversion: 'Contact & conversion',
    dynamic: 'Live content',
};
