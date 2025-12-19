import * as QRCode from 'qrcode';

type QrModulesOptions = { quietModules?: number; errorCorrectionLevel?: string };
type QrModulesResult = { modules: boolean[][]; moduleSize: number; moduleCount: number; offset: number };

export function createQrModules(
  link: string,
  canvasSize: number,
  options: QrModulesOptions = {},
): QrModulesResult | null {
  const text = typeof link === 'string' ? link.trim() : '';
  if (!text || !canvasSize) return null;

  const quietModules = Number.isFinite(options.quietModules)
    ? Math.max(0, Math.min(16, Math.floor(options.quietModules)))
    : 4;
  const errorCorrectionLevel = options.errorCorrectionLevel || 'H';

  let qr: ReturnType<typeof QRCode.create> | null;
  try {
    qr = QRCode.create(text, { errorCorrectionLevel });
  } catch (error) {
    return null;
  }

  const baseCount = qr?.modules?.size;
  if (!Number.isFinite(baseCount) || baseCount <= 0 || typeof qr.modules.get !== 'function') {
    return null;
  }

  const moduleCount = baseCount + quietModules * 2;
  const moduleSize = canvasSize / moduleCount;
  const offset = (canvasSize - moduleSize * moduleCount) / 2;

  const modules: boolean[][] = [];
  for (let row = 0; row < moduleCount; row += 1) {
    const rowValues: boolean[] = [];
    for (let col = 0; col < moduleCount; col += 1) {
      const baseRow = row - quietModules;
      const baseCol = col - quietModules;
      const isInside = baseRow >= 0 && baseCol >= 0 && baseRow < baseCount && baseCol < baseCount;
      rowValues.push(isInside ? !!qr.modules.get(baseRow, baseCol) : false);
    }
    modules.push(rowValues);
  }

  return {
    modules,
    moduleSize,
    moduleCount,
    offset,
  };
}
