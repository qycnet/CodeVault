/**
 * CodeVault - 快捷键支持
 */

// 快捷键配置
export interface ShortcutConfig {
  key: string;
  ctrl?: boolean;
  shift?: boolean;
  alt?: boolean;
  action: () => void;
  description: string;
}

class ShortcutManager {
  private shortcuts: Map<string, ShortcutConfig> = new Map();
  private enabled: boolean = true;
  
  constructor() {
    this.init();
  }
  
  private init(): void {
    document.addEventListener('keydown', this.handleKeyDown.bind(this));
  }
  
  private handleKeyDown(event: KeyboardEvent): void {
    if (!this.enabled) return;
    
    // 忽略输入框中的快捷键
    const target = event.target as HTMLElement;
    if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
      // 只允许 Escape 键
      if (event.key !== 'Escape') return;
    }
    
    const key = this.buildKey(event);
    const shortcut = this.shortcuts.get(key);
    
    if (shortcut) {
      event.preventDefault();
      shortcut.action();
    }
  }
  
  private buildKey(event: KeyboardEvent): string {
    const parts: string[] = [];
    
    if (event.ctrlKey || event.metaKey) parts.push('ctrl');
    if (event.shiftKey) parts.push('shift');
    if (event.altKey) parts.push('alt');
    
    parts.push(event.key.toLowerCase());
    
    return parts.join('+');
  }
  
  /**
   * 注册快捷键
   */
  register(config: ShortcutConfig): void {
    const key = this.buildKeyFromConfig(config);
    this.shortcuts.set(key, config);
  }
  
  /**
   * 批量注册快捷键
   */
  registerAll(configs: ShortcutConfig[]): void {
    configs.forEach(config => this.register(config));
  }
  
  /**
   * 取消注册快捷键
   */
  unregister(key: string, ctrl?: boolean, shift?: boolean, alt?: boolean): void {
    const keyStr = this.buildKeyFromConfig({ key, ctrl, shift, alt, action: () => {}, description: '' });
    this.shortcuts.delete(keyStr);
  }
  
  private buildKeyFromConfig(config: { key: string; ctrl?: boolean; shift?: boolean; alt?: boolean }): string {
    const parts: string[] = [];
    
    if (config.ctrl) parts.push('ctrl');
    if (config.shift) parts.push('shift');
    if (config.alt) parts.push('alt');
    
    parts.push(config.key.toLowerCase());
    
    return parts.join('+');
  }
  
  /**
   * 启用/禁用快捷键
   */
  setEnabled(enabled: boolean): void {
    this.enabled = enabled;
  }
  
  /**
   * 获取所有快捷键
   */
  getAll(): ShortcutConfig[] {
    return Array.from(this.shortcuts.values());
  }
  
  /**
   * 显示快捷键帮助
   */
  showHelp(): void {
    const shortcuts = this.getAll();
    const message = shortcuts
      .map(s => {
        const keys = [];
        if (s.ctrl) keys.push('Ctrl');
        if (s.shift) keys.push('Shift');
        if (s.alt) keys.push('Alt');
        keys.push(s.key.toUpperCase());
        return `${keys.join(' + ')}: ${s.description}`;
      })
      .join('\n');
    
    alert(`快捷键列表:\n\n${message}`);
  }
}

// 全局快捷键管理器
export const shortcutManager = new ShortcutManager();

// 默认快捷键
export const defaultShortcuts: ShortcutConfig[] = [
  { key: '/', ctrl: true, action: () => window.location.href = '/search', description: '搜索' },
  { key: 'n', ctrl: true, action: () => window.location.href = '/repos/new', description: '新建仓库' },
  { key: 'i', ctrl: true, action: () => window.location.href = '/issues', description: '我的 Issue' },
  { key: 'p', ctrl: true, action: () => window.location.href = '/pulls', description: '我的 PR' },
  { key: 'h', ctrl: true, action: () => window.location.href = '/', description: '首页' },
  { key: '?', shift: true, action: () => shortcutManager.showHelp(), description: '显示快捷键帮助' },
  { key: 'Escape', action: () => this.closeModal(), description: '关闭弹窗' },
];

// 初始化默认快捷键
shortcutManager.registerAll(defaultShortcuts);

function closeModal(): void {
  const closeBtn = document.querySelector('.el-dialog__close') as HTMLElement;
  if (closeBtn) {
    closeBtn.click();
  }
}
