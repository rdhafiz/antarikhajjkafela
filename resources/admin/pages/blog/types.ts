import type { Translations } from '@admin/types';

export type PostState = 'draft' | 'scheduled' | 'published';

export interface PostCategory {
    id: number;
    name: Translations;
    slug: string;
    posts_count: number;
}

export interface PostMeta {
    categories: PostCategory[];
    tags: string[];
}
