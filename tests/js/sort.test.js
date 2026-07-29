// Dragging and the arrow keys in the manual sort view. The reorder itself is tested in PHP,
// against a database; this is the half that only exists in a browser and that was until now
// only ever checked by hand.
const {test, describe} = require('node:test')
const assert = require('node:assert')
const {available, mount, fire} = require('./helpers/cms')

// Two groups in one table, which is what a list showing several menus looks like.
const TABLE = `
<div data-model="page">
	<table><tbody>
		<tr data-id="1"><td class="sort" data-sort-group="a"><button type="button"></button></td><td>one</td></tr>
		<tr data-id="2"><td class="sort" data-sort-group="a"><button type="button"></button></td><td>two</td></tr>
		<tr data-id="3"><td class="sort" data-sort-group="b"><button type="button"></button></td><td>three</td></tr>
	</tbody></table>
</div>`

function table(html = TABLE){
	const env = mount(html, ['CMS.list.sort'])
	env.rows = () => [...env.document.querySelectorAll('tr[data-id]')].map(tr => tr.dataset.id)
	env.row = id => env.document.querySelector(`tr[data-id="${id}"]`)
	env.grip = id => env.row(id).querySelector('td.sort')
	env.puts = () => env.calls.posts
	// The script asks the document what is under the pointer; jsdom has no layout, so a test
	// says which row that is.
	env.pointAt = row => env.window.document.elementFromPoint = () => row
	env.context.app.put = (...args) => env.calls.posts.push(args)
	return env
}

describe('manual sorting in the browser', {skip: !available && 'needs the Phlo engine and jsdom'}, () => {

	test('releasing without having moved anything sends nothing', () => {
		const env = table()
		fire(env, 'pointerdown', env.grip('1'))
		fire(env, 'pointerup', env.document.body)
		assert.strictEqual(env.puts().length, 0, 'a drag that changed nothing is not a change')
	})

	test('a drag within one group sends that group in its new order', () => {
		const env = table()
		fire(env, 'pointerdown', env.grip('1'))
		env.pointAt(env.row('2'))
		fire(env, 'pointermove', env.document.body, {clientX: 0, clientY: 1000})
		fire(env, 'pointerup', env.document.body)
		assert.deepStrictEqual(env.rows(), ['2', '1', '3'])
		const [url, payload] = env.puts().at(-1)
		assert.strictEqual(url, 'api/sort/page')
		assert.deepStrictEqual([...payload.ids], ['2', '1'], 'only the dragged row group goes along')
	})

	test('a row from another group is not a place to drop', () => {
		const env = table()
		fire(env, 'pointerdown', env.grip('1'))
		env.pointAt(env.row('3'))
		fire(env, 'pointermove', env.document.body, {clientX: 0, clientY: 1000})
		fire(env, 'pointerup', env.document.body)
		assert.deepStrictEqual(env.rows(), ['1', '2', '3'], 'crossing a group boundary says nothing about either order')
		assert.strictEqual(env.puts().length, 0)
	})

	test('a disabled handle does not start a drag at all', () => {
		const env = table(TABLE.replace('class="sort" data-sort-group="a"', 'class="sort sort--off" data-sort-group="a"'))
		fire(env, 'pointerdown', env.grip('1'))
		env.pointAt(env.row('2'))
		fire(env, 'pointermove', env.document.body, {clientX: 0, clientY: 1000})
		fire(env, 'pointerup', env.document.body)
		assert.deepStrictEqual(env.rows(), ['1', '2', '3'])
		assert.strictEqual(env.puts().length, 0)
	})

	test('the arrow keys move a row and save it, without a pointer anywhere', () => {
		const env = table()
		fire(env, 'keydown', env.grip('1').querySelector('button'), {key: 'ArrowDown'})
		assert.deepStrictEqual(env.rows(), ['2', '1', '3'])
		assert.deepStrictEqual([...env.puts().at(-1)[1].ids], ['2', '1'])
	})

	test('an arrow at the edge of its group does nothing', () => {
		const env = table()
		fire(env, 'keydown', env.grip('1').querySelector('button'), {key: 'ArrowUp'})
		assert.deepStrictEqual(env.rows(), ['1', '2', '3'], 'the first row of a group has nowhere above it')
		assert.strictEqual(env.puts().length, 0)
		fire(env, 'keydown', env.grip('3').querySelector('button'), {key: 'ArrowDown'})
		assert.deepStrictEqual(env.rows(), ['1', '2', '3'], 'and the only row of a group has nowhere at all')
		assert.strictEqual(env.puts().length, 0)
	})

	// Letting go outside the window used to leave the row stuck in its dragging state.
	test('a cancelled drag ends like a release does', () => {
		const env = table()
		fire(env, 'pointerdown', env.grip('1'))
		assert.ok(env.row('1').classList.contains('dragging'))
		env.pointAt(env.row('2'))
		fire(env, 'pointermove', env.document.body, {clientX: 0, clientY: 1000})
		fire(env, 'pointercancel', env.document.body)
		assert.ok(!env.row('1').classList.contains('dragging'), 'the row must not stay stuck mid drag')
		assert.deepStrictEqual([...env.puts().at(-1)[1].ids], ['2', '1'], 'and what was moved is still saved')
	})
})
