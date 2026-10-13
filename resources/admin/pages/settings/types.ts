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
    /** Selects that may be left empty. */
    clearable?: boolean;
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
    groups: SettingsNavGroup[];
}

export interface SettingsNavGroup {
    key: string;
    label: string;
    description: string;
    icon: string;
    /** Every field's name, for searching all settings from the hub. */
    fields: { key: string; label: string; section: string | null }[];
    filled: number;
    total: number;
}
