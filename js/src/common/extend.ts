import Extend from 'flarum/common/extenders';
import SeriesModel from './models/SeriesModel';

export default [new Extend.Store().add('anthology-series', SeriesModel)];
