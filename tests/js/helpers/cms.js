// The CMS is built on Phlo, so its frontend tests borrow the engine's loader rather than
// keeping a second copy. Same arrangement as the PHP tests: the engine is an external
// dependency, its location is configurable, and everything skips cleanly when it is absent.
const path = require('path')
const fs = require('fs')

const ENGINE = (process.env.PHLO_ENGINE_PATH || '/srv/control/phlo').replace(/\/$/, '')
const CMS = path.resolve(__dirname, '../../..')

const available = fs.existsSync(path.join(ENGINE, 'tests/js/helpers/dom.js')) && fs.existsSync(path.join(CMS, 'node_modules/jsdom'))

function mount(html, resources){
	const {mount} = require(path.join(ENGINE, 'tests/js/helpers/dom.js'))
	return mount(html, resources.map(r => r.startsWith('/') ? r : path.join(CMS, r + '.phlo')))
}

// The engine binds a selector handler to every matching element on update; a test reaches the
// same handler by name and drives it with the element it cares about.
function fire(env, event, element, detail = {}){
	const handlers = env.calls.events.filter(h => h.evts === event && typeof h.els === 'string')
	const matching = handlers.filter(h => element.matches(h.els) || element.closest(h.els))
	matching.forEach(h => h.cb(element.closest(h.els) || element, {preventDefault(){}, button: 0, ...detail}))
	return matching.length
}

module.exports = {available, mount, fire, ENGINE, CMS}
