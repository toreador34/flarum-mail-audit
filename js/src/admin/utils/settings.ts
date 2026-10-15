import app from 'flarum/admin/app';

export const SETTING_PREFIX = 'toreador-flarum-mail-audit.';

export function settingValue(key: string, fallback: any = null): any {
  const value = (app.data.settings as any)[SETTING_PREFIX + key];

  return value === undefined || value === null || value === '' ? fallback : value;
}

export function isSettingOn(key: string, fallback = true): boolean {
  const value = settingValue(key, undefined);

  if (value === undefined) return fallback;

  return value !== false && value !== '0' && value !== 0;
}
