import postcssUseLogical from 'postcss-use-logical';
import autoprefixer from 'autoprefixer';
import pxtorem from 'postcss-pxtorem';

import pxtoremConfig from './pxtorem.config.js';

export default (ctx) => {
  const useLogical = ctx.file && ctx.file.includes('bootstrap.scss');
  
  return {
    plugins: [
      ...(useLogical ? [postcssUseLogical] : []),
      pxtorem(pxtoremConfig),
      autoprefixer(),
    ],
  };
};