export interface SettingOption {
    value: string | number;
    label: string;
}

export interface SettingFieldDefinition {
    key: string;
    type: 'string' | 'text' | 'email' | 'url' | 'phone' | 'color' | 'media' | 'boolean' | 'select' | 'timezone' | 'json' | 'map' | 'secret';
    label: string;
    hint?: string;
    section?: string;
    translatable: boolean;
    readonly: boolean;
    usage?: string;
    options?: SettingOption[];
}

export interface SettingsGroup {
    key: string;
    label: string;
    description: string | null;
    fields: SettingFieldDefinition[];
    values: Record<string, unknown>;
}

export interface SettingsNavSection {
    label: string;
    groups: { key: string; label: string; description: string; icon: string }[];
}
